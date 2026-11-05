import asyncio
import logging
from contextlib import suppress
from typing import Any

from langgraph.checkpoint.postgres.aio import AsyncPostgresSaver

from finance_agent.graph import ChatReasoner, ModelNotConfigured, run_graph
from finance_agent.mcp_client import connect_tools, visible_messages
from finance_agent.models import Run
from finance_agent.schemas import Actor
from finance_agent.settings import Settings, get_settings
from finance_agent.store import Store, StoreError, make_engine

logger = logging.getLogger("finance_agent.worker")


async def execute(
    store: Store, settings: Settings, run: Run, actor: Actor, checkpointer: AsyncPostgresSaver
) -> None:
    while True:
        try:
            await execute_locked(store, settings, run, actor, checkpointer)
            return
        except StoreError as error:
            if error.code != "thread_busy":
                raise
        await asyncio.sleep(0.2)


async def execute_locked(
    store: Store, settings: Settings, run: Run, actor: Actor, checkpointer: AsyncPostgresSaver
) -> None:
    with store.execution_guard(run) as executable:
        if not executable:
            return
        async with connect_tools(settings, actor.actor_user_id) as tools:
            await tools.call("list_account_balances", {"limit": 1})
            if run.context.get("account_id"):
                await tools.call(
                    "get_account_balance", {"account_id": int(run.context["account_id"])}
                )
            history = await visible_messages(
                tools,
                store.recent_history(
                    run.thread_id,
                    run.id,
                    settings.agent_history_limit,
                ),
            )
            result = await run_graph(
                settings,
                tools,
                ChatReasoner(settings),
                run.thread_id,
                run.id,
                run.input_text,
                run.context,
                history,
                checkpointer,
                needs_title=store.needs_title(run.thread_id, actor),
            )
            if result.references:
                allowed = await visible_messages(tools, [{"references": result.references}])
                if not allowed:
                    store.finish(run.id, error="access_revoked")
                    return
            store.finish(
                run.id,
                result.content,
                result.references,
                result.warnings,
                conversation_title=result.conversation_title,
            )


async def supervise(store: Store, settings: Settings, run: Run, operation: Any) -> None:
    task = asyncio.create_task(operation)
    deadline = asyncio.get_running_loop().time() + settings.agent_run_timeout
    try:
        while not task.done():
            if asyncio.get_running_loop().time() >= deadline:
                task.cancel()
                store.finish(run.id, error="run_timeout")
                return
            if not store.heartbeat(run.id, settings.agent_lease_seconds):
                task.cancel()
                return
            await asyncio.wait({task}, timeout=min(2, settings.agent_lease_seconds / 3))
        await task
    except asyncio.CancelledError:
        task.cancel()
        store.finish(run.id, error="worker_interrupted")
        raise
    except Exception as error:
        logger.warning("agent_run_failed run_id=%s error_type=%s", run.id, type(error).__name__)
        code = (
            "model_not_configured"
            if isinstance(error, ModelNotConfigured)
            else "dependency_unavailable"
        )
        store.finish(run.id, error=code)
    finally:
        if not task.done():
            task.cancel()
        with suppress(asyncio.CancelledError, Exception):
            await task


async def main() -> None:
    settings = get_settings()
    store = Store(make_engine(settings.database_url))
    async with AsyncPostgresSaver.from_conn_string(settings.checkpoint_url) as checkpointer:
        while True:
            claimed = store.claim(settings.agent_lease_seconds)
            if claimed is None:
                await asyncio.sleep(1)
                continue
            run, actor = claimed
            await supervise(
                store, settings, run, execute(store, settings, run, actor, checkpointer)
            )


if __name__ == "__main__":
    logging.basicConfig(level=logging.WARNING)
    asyncio.run(main())
