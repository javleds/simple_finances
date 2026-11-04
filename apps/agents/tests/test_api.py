from typing import Any
from uuid import uuid4

import pytest
from fastapi.testclient import TestClient

from finance_agent.schemas import Actor
from finance_agent.store import Store


def make_thread(client: TestClient) -> str:
    response = client.post(
        "/internal/v1/threads",
        json={
            "actor_user_id": 1,
            "client_request_id": str(uuid4()),
        },
    )
    assert response.status_code == 201, response.text
    return str(response.json()["id"])


def run_body() -> dict[str, Any]:
    return {
        "actor_user_id": 1,
        "client_request_id": str(uuid4()),
        "message": "Saldo",
        "context": {"scope": "global", "account_id": None},
    }


def test_requires_service_credential(client: TestClient) -> None:
    assert (
        client.get(
            "/internal/v1/threads?actor_user_id=1", headers={"Authorization": "Bearer wrong"}
        ).status_code
        == 401
    )
    assert client.get("/health/live", headers={"Authorization": ""}).status_code == 200
    assert client.get("/health/ready").status_code == 200


@pytest.mark.parametrize(
    "payload",
    [
        {"actor_user_id": "1"},
        {"user_id": 2},
        {"message": " "},
        {"context": {"scope": "global", "account_id": "42"}},
        {"context": {"scope": "account", "account_id": "0"}},
        {"channel": "whatsapp", "context": {"scope": "account", "account_id": "42"}},
        {"tools": ["sql"]},
        {"message": "x" * 4001},
    ],
)
def test_rejects_invalid_or_extra_run_fields(client: TestClient, payload: dict[str, Any]) -> None:
    tid = make_thread(client)
    assert (
        client.post(f"/internal/v1/threads/{tid}/runs", json={**run_body(), **payload}).status_code
        == 422
    )


def test_owner_required_for_sse_history_poll_and_cancel(client: TestClient) -> None:
    tid = make_thread(client)
    rid = client.post(f"/internal/v1/threads/{tid}/runs", json=run_body()).json()["run_id"]
    base = f"/internal/v1/threads/{tid}"
    for path in [f"{base}/messages", f"{base}/runs/{rid}", f"{base}/runs/{rid}/events"]:
        assert client.get(path, params={"actor_user_id": 2}).status_code == 404
    assert client.post(f"{base}/runs/{rid}/cancel", json={"actor_user_id": 2}).status_code == 404


def test_revoked_references_hidden_in_history_poll_replay_and_sse(
    client: TestClient,
    store: Store,
    tools: Any,
) -> None:
    tid, body = make_thread(client), run_body()
    base = f"/internal/v1/threads/{tid}"
    rid = client.post(f"{base}/runs", json=body).json()["run_id"]
    assert store.claim(30)
    store.finish(rid, "Private $999", [{"account_id": "42"}])
    tools.denied.add(42)
    assert client.get(f"{base}/messages", params={"actor_user_id": 1}).json()["items"] == []
    for response in [
        client.get(f"{base}/runs/{rid}", params={"actor_user_id": 1}),
        client.post(f"{base}/runs", json=body),
    ]:
        assert response.json()["message"] is None
        assert "Private" not in response.text
    stream = client.get(f"{base}/runs/{rid}/events", params={"actor_user_id": 1, "after": 2})
    assert "Private" not in stream.text
    assert "id: 3" in stream.text and "event: run.completed" in stream.text


def test_context_access_checked_before_run_created(client: TestClient, tools: Any) -> None:
    tid = make_thread(client)
    tools.denied.add(42)
    response = client.post(
        f"/internal/v1/threads/{tid}/runs",
        json={
            **run_body(),
            "context": {"scope": "account", "account_id": "42"},
        },
    )
    assert response.status_code == 403
    assert (
        client.get(f"/internal/v1/threads/{tid}/messages", params={"actor_user_id": 1}).json()[
            "items"
        ]
        == []
    )


def test_sse_resume_and_cancel(client: TestClient, store: Store) -> None:
    tid = make_thread(client)
    rid = client.post(f"/internal/v1/threads/{tid}/runs", json=run_body()).json()["run_id"]
    assert store.claim(30)
    store.cancel(tid, rid, Actor(actor_user_id=1))
    response = client.get(
        f"/internal/v1/threads/{tid}/runs/{rid}/events", params={"actor_user_id": 1, "after": 2}
    )
    assert response.headers["x-accel-buffering"] == "no"
    assert "run.started" not in response.text
    assert "run.cancelled" in response.text
