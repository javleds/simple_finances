import os
from concurrent.futures import ThreadPoolExecutor
from datetime import timedelta
from uuid import uuid4

import pytest
from langgraph.checkpoint.postgres.aio import AsyncPostgresSaver
from sqlalchemy import delete, text
from sqlalchemy.exc import DBAPIError
from test_graph import FakeModel

from finance_agent.graph import run_graph
from finance_agent.models import Run, Thread, now
from finance_agent.schemas import Actor, CreateRun, CreateThread
from finance_agent.settings import Settings
from finance_agent.store import Store, StoreError, make_engine

URL = os.environ.get("AGENT_TEST_DATABASE_URL")
pytestmark = pytest.mark.skipif(not URL, reason="Requires ephemeral migrated PostgreSQL")


@pytest.fixture
def pg_store():
    store = Store(make_engine(str(URL)))
    yield store
    with store.sessions.begin() as session:
        session.execute(delete(Thread).where(Thread.owner_user_id == 90001))
    store.engine.dispose()


def test_concurrent_acceptance_restart_and_runtime_no_ddl(pg_store: Store) -> None:
    actor = Actor(actor_user_id=90001)
    tid = pg_store.create_thread(CreateThread(actor_user_id=90001, client_request_id=uuid4()))["id"]

    def submit() -> str:
        try:
            return str(
                pg_store.create_run(
                    tid,
                    CreateRun(
                        actor_user_id=90001,
                        client_request_id=uuid4(),
                        message="Saldo",
                    ),
                )["run_id"]
            )
        except StoreError as error:
            return error.code

    with ThreadPoolExecutor(max_workers=2) as pool:
        results = list(pool.map(lambda _: submit(), range(2)))
    assert results.count("run_active") == 1
    rid = next(value for value in results if value != "run_active")
    claimed = pg_store.claim(30)
    assert claimed and claimed[0].id == rid
    restarted = Store(make_engine(str(URL)))
    assert restarted.get_run(tid, rid, actor)["status"] == "running"
    with pg_store.sessions.begin() as session:
        run = session.get(Run, rid)
        assert run
        run.lease_until = now() - timedelta(seconds=1)
    assert restarted.claim(30) is None
    assert restarted.get_run(tid, rid, actor)["error"] == "worker_interrupted"
    with pytest.raises(DBAPIError), pg_store.engine.begin() as connection:
        connection.execute(text("CREATE TABLE unauthorized_ddl (id integer)"))
    restarted.engine.dispose()


def test_polling_and_finish_do_not_deadlock(pg_store: Store) -> None:
    actor = Actor(actor_user_id=90001)
    tid = pg_store.create_thread(CreateThread(actor_user_id=90001, client_request_id=uuid4()))["id"]
    rid = pg_store.create_run(
        tid, CreateRun(actor_user_id=90001, client_request_id=uuid4(), message="Saldo")
    )["run_id"]
    assert pg_store.claim(30)

    def poll() -> None:
        for _ in range(30):
            pg_store.get_run(tid, rid, actor)

    with ThreadPoolExecutor(max_workers=2) as pool:
        future = pool.submit(poll)
        assert pg_store.finish(rid, "Completado")
        future.result(timeout=10)
    assert pg_store.get_run(tid, rid, actor)["status"] == "completed"


async def test_checkpoint_reopens_with_runtime_role(pg_store: Store, tools) -> None:
    tid = pg_store.create_thread(CreateThread(actor_user_id=90001, client_request_id=uuid4()))["id"]
    checkpoint_url = str(URL).replace("postgresql+psycopg://", "postgresql://")
    config = {"configurable": {"thread_id": tid}}
    async with AsyncPostgresSaver.from_conn_string(checkpoint_url) as saver:
        result = await run_graph(
            Settings(),
            tools,
            FakeModel(),
            tid,
            str(uuid4()),
            "Saldo",
            {"scope": "global"},
            [],
            saver,
        )
        assert result.references == [{"account_id": "42"}]
    async with AsyncPostgresSaver.from_conn_string(checkpoint_url) as saver:
        checkpoints = [item async for item in saver.alist(config)]
        assert checkpoints
        assert "test-service" not in str(checkpoints)
        await saver.adelete_thread(tid)
        assert [item async for item in saver.alist(config)] == []
