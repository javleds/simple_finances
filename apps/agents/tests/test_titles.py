from typing import Any
from uuid import uuid4

import pytest
from fastapi.testclient import TestClient

from finance_agent.graph import Answer
from finance_agent.models import Thread
from finance_agent.schemas import Actor, CreateRun, CreateThread
from finance_agent.store import Store


@pytest.mark.parametrize(
    "title",
    [
        None,
        42,
        {},
        "Saldo",
        "a " * 41,
        "Gastos\nde este mes",
        "<b>Gastos de enero</b>",
        "Gastos de cuenta 42",
        "Gastos en https://example.com",
        "**Gastos de enero**",
    ],
)
def test_invalid_optional_title_does_not_invalidate_answer(title: Any) -> None:
    answer = Answer.model_validate({"content": "Respuesta correcta", "conversation_title": title})
    assert answer.content == "Respuesta correcta"
    assert answer.conversation_title is None


def create_run(store: Store, tid: str) -> str:
    run = store.create_run(
        tid,
        CreateRun(
            actor_user_id=1,
            client_request_id=uuid4(),
            message="Consultar mis gastos",
        ),
    )
    assert store.claim(30)
    return str(run["run_id"])


def test_first_valid_title_persists_once_with_evidence(store: Store) -> None:
    request = CreateThread(actor_user_id=1, client_request_id=uuid4())
    tid = store.create_thread(request)["id"]
    assert store.needs_title(tid, Actor(actor_user_id=1))
    first = create_run(store, tid)
    assert store.finish(first, "Respuesta", conversation_title="Invalid")
    assert store.needs_title(tid, Actor(actor_user_id=1))
    second = create_run(store, tid)
    refs = [{"account_id": "42"}]
    assert store.finish(
        second, "Respuesta", refs, conversation_title="Gastos de enero por categoría"
    )
    assert not store.needs_title(tid, Actor(actor_user_id=1))
    third = create_run(store, tid)
    assert store.finish(third, "Respuesta", [], conversation_title="Otro título de conversación")
    replay = store.create_thread(request)
    assert replay["title"] == "Gastos de enero por categoría"
    assert replay["title_references"] == refs
    assert replay["id"] == tid


@pytest.mark.parametrize("terminal", ["cancelled", "failed"])
def test_terminal_failure_never_persists_title(store: Store, terminal: str) -> None:
    tid = store.create_thread(CreateThread(actor_user_id=1, client_request_id=uuid4()))["id"]
    rid = create_run(store, tid)
    if terminal == "cancelled":
        store.cancel(tid, rid, Actor(actor_user_id=1))
        assert not store.finish(rid, "Late", conversation_title="Gastos de este mes")
    else:
        assert store.finish(rid, error="access_revoked", conversation_title="Gastos de este mes")
    with store.sessions() as session:
        thread = session.get(Thread, tid)
        assert thread and thread.title is None and thread.title_references == []
    assert store.get_run(tid, rid, Actor(actor_user_id=1))["message"] is None


def test_titles_reauthorized_on_listing_and_idempotent_creation(
    client: TestClient,
    store: Store,
    tools: Any,
) -> None:
    request = {"actor_user_id": 1, "client_request_id": str(uuid4())}
    tid = client.post("/internal/v1/threads", json=request).json()["id"]
    for _ in range(2):
        current = (
            tid
            if _ == 0
            else store.create_thread(CreateThread(actor_user_id=1, client_request_id=uuid4()))["id"]
        )
        store.finish(
            create_run(store, current),
            "Respuesta",
            [{"account_id": "42"}],
            conversation_title="Gastos de enero por categoría",
        )
    tools.calls.clear()
    response = client.get("/internal/v1/threads", params={"actor_user_id": 1})
    assert all(row["title"] == "Gastos de enero por categoría" for row in response.json()["items"])
    assert "title_references" not in response.text
    assert sum(name == "get_account_balance" for name, _ in tools.calls) == 1
    tools.denied.add(42)
    for response in [
        client.get("/internal/v1/threads", params={"actor_user_id": 1}),
        client.post("/internal/v1/threads", json=request),
    ]:
        assert response.status_code in {200, 201}
        assert "Gastos" not in response.text and "title_references" not in response.text
    assert client.get("/internal/v1/threads", params={"actor_user_id": 2}).json()["items"] == []
    assert (
        client.get(
            "/internal/v1/threads", params={"actor_user_id": 1, "channel": "whatsapp"}
        ).json()["items"]
        == []
    )


def test_migration_preserves_existing_threads() -> None:
    import importlib.util
    from pathlib import Path

    from alembic.migration import MigrationContext
    from alembic.operations import Operations
    from sqlalchemy import create_engine, text

    engine = create_engine("sqlite://")
    migrations = Path(__file__).parents[1] / "migrations" / "versions"
    with engine.begin() as connection:
        for name in ["001_conversations", "002_conversation_titles"]:
            module_spec = importlib.util.spec_from_file_location(name, migrations / f"{name}.py")
            assert module_spec and module_spec.loader
            module = importlib.util.module_from_spec(module_spec)
            module_spec.loader.exec_module(module)
            with Operations.context(MigrationContext.configure(connection)):
                module.upgrade()
            if name == "001_conversations":
                connection.execute(
                    text(
                        "INSERT INTO agent_threads VALUES "
                        "('legacy', 1, 'web', 'request', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
                    )
                )
        row = connection.execute(
            text("SELECT id, title, title_references FROM agent_threads")
        ).one()
        assert tuple(row) == ("legacy", None, "[]")
    engine.dispose()


async def test_graph_title_requested_only_when_needed(tools: Any) -> None:
    from langchain_core.messages import BaseMessage
    from test_graph import FakeModel

    from finance_agent.graph import run_graph
    from finance_agent.settings import Settings

    class TitleModel(FakeModel):
        async def answer(self, messages: list[BaseMessage]) -> Answer:
            return Answer(content="Respuesta correcta", conversation_title="Balance de mis cuentas")

    for needs_title in [True, False]:
        model = TitleModel()
        result = await run_graph(
            Settings(),
            tools,
            model,
            str(uuid4()),
            str(uuid4()),
            "Saldo",
            {"scope": "global"},
            [],
            needs_title=needs_title,
        )
        assert result.conversation_title == ("Balance de mis cuentas" if needs_title else None)
        assert ("Incluye conversation_title" in str(model.seen[0].content)) == needs_title
        assert result.references == [{"account_id": "42"}]


@pytest.mark.parametrize("revoked", [False, True])
async def test_worker_publishes_title_only_after_reference_authorization(
    store: Store,
    tools: Any,
    monkeypatch: pytest.MonkeyPatch,
    revoked: bool,
) -> None:
    from collections.abc import AsyncIterator
    from contextlib import asynccontextmanager

    from finance_agent import worker
    from finance_agent.graph import GraphResult
    from finance_agent.settings import Settings

    tid = store.create_thread(CreateThread(actor_user_id=1, client_request_id=uuid4()))["id"]
    rid = store.create_run(
        tid,
        CreateRun(
            actor_user_id=1,
            client_request_id=uuid4(),
            message="Consultar mis gastos",
        ),
    )["run_id"]
    claimed = store.claim(30)
    assert claimed

    @asynccontextmanager
    async def connector(*_: Any) -> AsyncIterator[Any]:
        yield tools

    async def graph(*args: Any, **kwargs: Any) -> GraphResult:
        assert kwargs["needs_title"]
        assert args[3] == tid
        if revoked:
            tools.denied.add(42)
        return GraphResult(
            content="Respuesta",
            references=[{"account_id": "42"}],
            warnings=[],
            conversation_title="Gastos de enero por categoría",
        )

    monkeypatch.setattr(worker, "connect_tools", connector)
    monkeypatch.setattr(worker, "ChatReasoner", lambda _: object())
    monkeypatch.setattr(worker, "run_graph", graph)
    await worker.execute(store, Settings(), claimed[0], claimed[1], None)  # type: ignore[arg-type]
    replay = store.list_threads(Actor(actor_user_id=1), None)["items"][0]
    result = store.get_run(tid, rid, Actor(actor_user_id=1))
    if revoked:
        assert replay["title"] is None and result["error"] == "access_revoked"
        assert result["message"] is None
        return
    assert replay["title"] == "Gastos de enero por categoría"
    assert replay["title_references"] == [{"account_id": "42"}]
    assert result["status"] == "completed"
