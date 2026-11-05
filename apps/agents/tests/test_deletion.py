from uuid import uuid4

import pytest
from fastapi.testclient import TestClient
from sqlalchemy import func, select

from finance_agent.models import Event, Message, Run, Thread
from finance_agent.schemas import Actor, CreateRun, CreateThread
from finance_agent.store import Store, StoreError

ACTOR = Actor(actor_user_id=5)


def conversation(store: Store) -> str:
    return str(store.create_thread(CreateThread(actor_user_id=5, client_request_id=uuid4()))["id"])


def test_delete_physically_cascades_and_preserves_other_threads(store: Store) -> None:
    tid, other = conversation(store), conversation(store)
    rid = store.create_run(
        tid, CreateRun(actor_user_id=5, client_request_id=uuid4(), message="Saldo")
    )["run_id"]
    assert store.claim(30)
    store.finish(rid, "Respuesta", conversation_title="Balance de mis cuentas")
    store.delete_thread(tid, ACTOR)
    with store.sessions() as session:
        assert session.get(Thread, tid) is None
        assert session.get(Thread, other) is not None
        for model in (Run, Message, Event):
            assert session.scalar(select(func.count()).select_from(model)) == 0
    with pytest.raises(StoreError, match="not_found"):
        store.delete_thread(tid, ACTOR)
    assert not store.finish(rid, "Late")


@pytest.mark.parametrize(
    "actor", [Actor(actor_user_id=6), Actor(actor_user_id=5, channel="whatsapp")]
)
def test_delete_hides_foreign_thread(store: Store, actor: Actor) -> None:
    tid = conversation(store)
    with pytest.raises(StoreError) as error:
        store.delete_thread(tid, actor)
    assert error.value.status == 404
    assert store.list_threads(ACTOR, None)["items"]


def test_delete_rejects_active_then_accepts_cancelled(store: Store) -> None:
    tid = conversation(store)
    rid = store.create_run(
        tid, CreateRun(actor_user_id=5, client_request_id=uuid4(), message="Saldo")
    )["run_id"]
    with pytest.raises(StoreError) as error:
        store.delete_thread(tid, ACTOR)
    assert error.value.status == 409
    claimed = store.claim(30)
    assert claimed
    store.cancel(tid, rid, ACTOR)
    store.delete_thread(tid, ACTOR)
    with store.execution_guard(claimed[0]) as allowed:
        assert not allowed


def test_delete_api_without_financial_dependency(client: TestClient, store: Store) -> None:
    tid = conversation(store)

    def unavailable(*args: object) -> None:
        raise AssertionError("Deletion must not contact MCP")

    client.app.state.connect_tools = unavailable
    response = client.request("DELETE", f"/internal/v1/threads/{tid}", json=ACTOR.model_dump())
    assert response.status_code == 204
    assert response.content == b""
    assert (
        client.request("DELETE", f"/internal/v1/threads/{tid}", json=ACTOR.model_dump()).status_code
        == 404
    )


def test_delete_api_authentication_and_validation(client: TestClient, store: Store) -> None:
    tid = conversation(store)
    path = f"/internal/v1/threads/{tid}"
    assert (
        client.request(
            "DELETE", path, json=ACTOR.model_dump(), headers={"Authorization": "Bearer invalid"}
        ).status_code
        == 401
    )
    assert client.request("DELETE", path, json={"actor_user_id": 0}).status_code == 422
    assert (
        client.request(
            "DELETE", "/internal/v1/threads/not-uuid", json=ACTOR.model_dump()
        ).status_code
        == 422
    )
    assert client.request("DELETE", path, json={"actor_user_id": 6}).status_code == 404


def test_head_authorizes_owner_without_mcp(client: TestClient, store: Store) -> None:
    tid = conversation(store)

    def unavailable(*args: object) -> None:
        raise AssertionError("Ownership lookup must not contact MCP")

    client.app.state.connect_tools = unavailable
    path = f"/internal/v1/threads/{tid}"
    assert client.head(path, params=ACTOR.model_dump()).status_code == 204
    assert client.head(path, params={"actor_user_id": 6}).status_code == 404
    assert client.head(path, params={"actor_user_id": 5, "channel": "whatsapp"}).status_code == 404
    assert client.head(path, params={"actor_user_id": 0}).status_code == 422
    assert client.head(path, params={"actor_user_id": 5, "channel": "other"}).status_code == 422
    assert (
        client.head(
            path, params=ACTOR.model_dump(), headers={"Authorization": "Bearer invalid"}
        ).status_code
        == 401
    )
    store.delete_thread(tid, ACTOR)
    assert client.head(path, params=ACTOR.model_dump()).status_code == 404
