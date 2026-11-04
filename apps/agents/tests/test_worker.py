import asyncio
from typing import Any
from uuid import uuid4

from finance_agent.schemas import Actor, CreateRun, CreateThread
from finance_agent.settings import Settings
from finance_agent.store import Store
from finance_agent.worker import supervise


async def test_cancellation_interrupts_operation_without_publishing(store: Store) -> None:
    tid = store.create_thread(CreateThread(actor_user_id=1, client_request_id=uuid4()))["id"]
    rid = store.create_run(
        tid, CreateRun(actor_user_id=1, client_request_id=uuid4(), message="Saldo")
    )["run_id"]
    claimed = store.claim(30)
    assert claimed
    interrupted = asyncio.Event()

    async def operation() -> None:
        try:
            await asyncio.sleep(30)
            store.finish(rid, "Late")
        finally:
            interrupted.set()

    store.cancel(tid, rid, Actor(actor_user_id=1))
    await supervise(store, Settings(), claimed[0], operation())
    assert store.get_run(tid, rid, Actor(actor_user_id=1))["status"] == "cancelled"


async def test_failure_does_not_persist_exception_secrets(store: Store) -> None:
    tid = store.create_thread(CreateThread(actor_user_id=1, client_request_id=uuid4()))["id"]
    rid = store.create_run(
        tid, CreateRun(actor_user_id=1, client_request_id=uuid4(), message="Saldo")
    )["run_id"]
    claimed = store.claim(30)
    assert claimed

    async def operation() -> None:
        raise RuntimeError("private provider credential")

    await supervise(store, Settings(), claimed[0], operation())
    result = store.get_run(tid, rid, Actor(actor_user_id=1))
    assert result["error"] == "dependency_unavailable"
    assert "private" not in str(store.events(tid, rid, Actor(actor_user_id=1), 0))


async def test_tool_identity_cannot_be_overridden() -> None:
    from finance_agent.mcp_client import McpTools, ToolDenied

    class Session:
        async def call_tool(self, *_: Any) -> None:
            raise AssertionError("Identity override reached MCP")

    import pytest

    tools = McpTools(Session())  # type: ignore[arg-type]
    for name, args in [
        ("execute_sql", {}),
        ("get_account_balance", {"actor_user_id": 9}),
        ("get_account_balance", {"user_id": 9}),
    ]:
        with pytest.raises(ToolDenied):
            await tools.call(name, args)
