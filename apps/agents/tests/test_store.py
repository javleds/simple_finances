from datetime import timedelta
from uuid import uuid4

import pytest
from sqlalchemy import select

from finance_agent.cleanup import cleanup
from finance_agent.models import Message, Run, Thread, now
from finance_agent.schemas import Actor, CreateRun, CreateThread
from finance_agent.store import Store, StoreError

ACTOR = Actor(actor_user_id=1)


def thread(store: Store) -> str:
    return str(store.create_thread(CreateThread(actor_user_id=1, client_request_id=uuid4()))["id"])


def request(message: str = "Saldo", **kwargs: object) -> CreateRun:
    return CreateRun(actor_user_id=1, client_request_id=uuid4(), message=message, **kwargs)


def test_thread_create_idempotency_and_channel(store: Store) -> None:
    body = CreateThread(actor_user_id=1, client_request_id=uuid4())
    assert store.create_thread(body) == store.create_thread(body)
    second = body.model_copy(update={"channel": "whatsapp"})
    assert store.create_thread(second)["id"] != store.create_thread(body)["id"]
    assert len(store.list_threads(ACTOR, None)["items"]) == 1


def test_run_idempotency_conflict_and_one_active(store: Store) -> None:
    tid, body = thread(store), request()
    first = store.create_run(tid, body)
    assert store.create_run(tid, body) == first
    with pytest.raises(StoreError, match="idempotency_conflict"):
        store.create_run(tid, body.model_copy(update={"message": "Otro"}))
    with pytest.raises(StoreError, match="run_active"):
        store.create_run(tid, request())


@pytest.mark.parametrize(
    "actor", [Actor(actor_user_id=2), Actor(actor_user_id=1, channel="whatsapp")]
)
def test_ownership_all_storage_operations(store: Store, actor: Actor) -> None:
    tid = thread(store)
    run = store.create_run(tid, request())
    for operation in [
        lambda: store.get_run(tid, run["run_id"], actor),
        lambda: store.history(tid, actor),
        lambda: store.cancel(tid, run["run_id"], actor),
        lambda: store.events(tid, run["run_id"], actor, 0),
    ]:
        with pytest.raises(StoreError, match="not_found"):
            operation()


def test_cancel_is_terminal_and_prevents_late_answer(store: Store) -> None:
    tid = thread(store)
    rid = store.create_run(tid, request())["run_id"]
    assert store.claim(30)
    store.cancel(tid, rid, ACTOR)
    store.cancel(tid, rid, ACTOR)
    assert not store.finish(rid, "Late confidential result")
    events, terminal = store.events(tid, rid, ACTOR, 0)
    assert terminal
    assert [e["type"] for e in events] == ["run.started", "status", "run.cancelled"]


def test_expired_lease_marks_interrupted_without_repeating(store: Store) -> None:
    tid = thread(store)
    rid = store.create_run(tid, request())["run_id"]
    assert store.claim(30)
    with store.sessions.begin() as session:
        run = session.get(Run, rid)
        assert run
        run.lease_until = now() - timedelta(seconds=1)
    assert store.claim(30) is None
    result = store.get_run(tid, rid, ACTOR)
    assert result["status"] == "failed"
    assert result["error"] == "worker_interrupted"
    assert not store.finish(rid, "Late")


def test_final_message_and_event_are_persisted_and_replayed(store: Store) -> None:
    tid = thread(store)
    rid = store.create_run(tid, request())["run_id"]
    assert store.claim(30)
    store.finish(rid, "Saldo $125.50", [{"account_id": "42"}])
    restarted = Store(store.engine)
    result = restarted.get_run(tid, rid, ACTOR)
    assert result["message"]["content"] == "Saldo $125.50"
    events, done = restarted.events(tid, rid, ACTOR, 2)
    assert done and [e["sequence"] for e in events] == [3, 4]
    assert events[0]["payload"] == result["message"]
    assert restarted.history(tid, ACTOR)["items"][0]["references"] == [{"account_id": "42"}]


def test_history_returns_newest_page_chronological_and_older_cursor(store: Store) -> None:
    tid = thread(store)
    rid = store.create_run(tid, request())["run_id"]
    with store.sessions.begin() as session:
        for index in range(105):
            session.add(
                Message(
                    thread_id=tid,
                    run_id=rid,
                    role="assistant",
                    content=str(index),
                    context={},
                    created_at=now() + timedelta(seconds=index + 1),
                )
            )
    latest = store.history(tid, ACTOR)
    assert len(latest["items"]) == 100
    assert latest["items"][0]["content"] == "5"
    assert latest["items"][-1]["content"] == "104"
    older = store.history(tid, ACTOR, latest["next_cursor"])
    assert len(older["items"]) == 6
    assert older["items"][-1]["content"] == "4"
    assert older["next_cursor"] is None


async def test_cleanup_preserves_active_threads_and_deletes_checkpoints(store: Store) -> None:
    inactive, active = thread(store), thread(store)
    store.create_run(active, request())
    with store.sessions.begin() as session:
        for row in session.scalars(select(Thread)):
            row.updated_at = now() - timedelta(days=31)

    class Saver:
        def __init__(self) -> None:
            self.deleted: list[str] = []

        async def adelete_thread(self, thread_id: str) -> None:
            self.deleted.append(thread_id)

    saver = Saver()
    assert await cleanup(store, saver, 30) == 1
    assert saver.deleted == [inactive]
    assert store.list_threads(ACTOR, None)["items"][0]["id"] == active
