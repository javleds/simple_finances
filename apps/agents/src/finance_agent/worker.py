import asyncio
import logging
import signal
from contextlib import suppress
from typing import Any

from langchain_core.messages import AIMessage, HumanMessage
from langgraph.checkpoint.postgres.aio import AsyncPostgresSaver

from finance_agent.conversation_context import ScopedTools, resolve_context
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
            history = await visible_messages(
                tools,
                store.recent_history(
                    run.thread_id,
                    run.id,
                    settings.agent_history_limit,
                ),
            )
            reasoner = ChatReasoner(settings)
            resolved = await resolve_context(
                tools,
                reasoner,
                store.conversation_context(run.thread_id, actor),
                run.context,
                run.input_text,
                [
                    (HumanMessage if item["role"] == "user" else AIMessage)(content=item["content"])
                    for item in history
                ],
            )
            if resolved.clarification:
                if not await visible_messages(tools, [{"references": resolved.state.references()}]):
                    store.finish(run.id, error="access_revoked")
                    return
                store.finish(
                    run.id,
                    resolved.clarification,
                    resolved.state.references(),
                    conversation_context=resolved.state,
                )
                return
            scoped_tools = ScopedTools(tools, resolved.state)
            result = await run_graph(
                settings,
                scoped_tools,
                reasoner,
                run.thread_id,
                run.id,
                resolved.question,
                resolved.state.public(),
                history,
                checkpointer,
                needs_title=store.needs_title(run.thread_id, actor),
            )
            if resolved.state.scope in {"global", "unset"} and scoped_tools.observed_accounts:
                resolved.state = resolved.state.model_copy(
                    update={
                        "scope": "global",
                        "candidates": list(scoped_tools.observed_accounts.values()),
                    }
                )
            result.references = [*result.references, *resolved.state.references()]
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
                conversation_context=resolved.state,
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


async def consume(
    store: Store,
    settings: Settings,
    checkpointer: AsyncPostgresSaver,
    shutdown: asyncio.Event,
) -> None:
    while not shutdown.is_set():
        claimed = store.claim(settings.agent_lease_seconds)
        if claimed is None:
            try:
                await asyncio.wait_for(shutdown.wait(), timeout=1)
            except TimeoutError:
                pass
            continue
        run, actor = claimed
        await supervise(store, settings, run, execute(store, settings, run, actor, checkpointer))


async def main() -> None:
    shutdown = asyncio.Event()
    loop = asyncio.get_running_loop()

    def request_shutdown() -> None:
        logger.warning("worker_shutdown_requested: finishing current run before exit")
        shutdown.set()

    for shutdown_signal in (signal.SIGTERM, signal.SIGINT):
        loop.add_signal_handler(shutdown_signal, request_shutdown)
    try:
        settings = get_settings()
        store = Store(make_engine(settings.database_url))
        async with AsyncPostgresSaver.from_conn_string(settings.checkpoint_url) as checkpointer:
            await consume(store, settings, checkpointer, shutdown)
    finally:
        for shutdown_signal in (signal.SIGTERM, signal.SIGINT):
            loop.remove_signal_handler(shutdown_signal)


if __name__ == "__main__":
    logging.basicConfig(level=logging.WARNING)
    asyncio.run(main())
