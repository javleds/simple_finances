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


async def test_physical_deletion_removes_all_checkpoint_data_atomically(
    pg_store: Store, tools
) -> None:
    from sqlalchemy import event

    actor = Actor(actor_user_id=90001)
    tid = pg_store.create_thread(CreateThread(actor_user_id=90001, client_request_id=uuid4()))["id"]
    other = pg_store.create_thread(CreateThread(actor_user_id=90001, client_request_id=uuid4()))[
        "id"
    ]
    rid = pg_store.create_run(
        tid, CreateRun(actor_user_id=90001, client_request_id=uuid4(), message="Saldo")
    )["run_id"]
    assert pg_store.claim(30)
    checkpoint_url = str(URL).replace("postgresql+psycopg://", "postgresql://")
    async with AsyncPostgresSaver.from_conn_string(checkpoint_url) as saver:
        for thread_id in (tid, other):
            await run_graph(
                Settings(),
                tools,
                FakeModel(),
                thread_id,
                str(uuid4()),
                "Saldo",
                {"scope": "global"},
                [],
                saver,
            )
        pg_store.finish(rid, "Completado")
        tables = ("checkpoints", "checkpoint_blobs", "checkpoint_writes")
        with pg_store.engine.connect() as connection:
            for table in tables:
                assert (
                    connection.scalar(
                        text(f"SELECT count(*) FROM {table} WHERE thread_id = :id"), {"id": tid}
                    )
                    > 0
                )

        def fail_delete(conn, cursor, statement, parameters, context, executemany):
            if statement.startswith("DELETE FROM checkpoints "):
                raise RuntimeError("simulated checkpoint failure")

        event.listen(pg_store.engine, "before_cursor_execute", fail_delete)
        try:
            with pytest.raises(RuntimeError, match="simulated checkpoint failure"):
                pg_store.delete_thread(tid, actor)
        finally:
            event.remove(pg_store.engine, "before_cursor_execute", fail_delete)
        with pg_store.engine.connect() as connection:
            for table in tables:
                assert (
                    connection.scalar(
                        text(f"SELECT count(*) FROM {table} WHERE thread_id = :id"), {"id": tid}
                    )
                    > 0
                )
        assert pg_store.history(tid, actor)["items"]
        pg_store.delete_thread(tid, actor)
        with pg_store.engine.connect() as connection:
            for table in (*tables, "agent_threads", "agent_runs", "agent_messages"):
                column = "id" if table == "agent_threads" else "thread_id"
                assert (
                    connection.scalar(
                        text(f"SELECT count(*) FROM {table} WHERE {column} = :id"), {"id": tid}
                    )
                    == 0
                )
            assert (
                connection.scalar(
                    text("SELECT count(*) FROM agent_events WHERE run_id = :id"), {"id": rid}
                )
                == 0
            )
        assert [item async for item in saver.alist({"configurable": {"thread_id": other}})]
        pg_store.delete_thread(other, actor)


def test_cancelled_worker_lock_blocks_delete_and_cleanup(pg_store: Store) -> None:
    actor = Actor(actor_user_id=90001)
    tid = pg_store.create_thread(CreateThread(actor_user_id=90001, client_request_id=uuid4()))["id"]
    rid = pg_store.create_run(
        tid, CreateRun(actor_user_id=90001, client_request_id=uuid4(), message="Saldo")
    )["run_id"]
    claimed = pg_store.claim(30)
    assert claimed
    with pg_store.execution_guard(claimed[0]) as allowed:
        assert allowed
        pg_store.cancel(tid, rid, actor)
        with pg_store.sessions.begin() as session:
            thread = session.get(Thread, tid)
            assert thread
            thread.updated_at = now() - timedelta(days=31)
        with pytest.raises(StoreError) as error:
            pg_store.delete_thread(tid, actor)
        assert error.value.status == 409
        assert pg_store.purge(30) == []
    assert pg_store.purge(30) == [tid]
    with pg_store.execution_guard(claimed[0]) as allowed:
        assert not allowed


def test_claimed_then_cancelled_and_deleted_worker_cannot_start(pg_store: Store) -> None:
    actor = Actor(actor_user_id=90001)
    tid = pg_store.create_thread(CreateThread(actor_user_id=90001, client_request_id=uuid4()))["id"]
    rid = pg_store.create_run(
        tid, CreateRun(actor_user_id=90001, client_request_id=uuid4(), message="Saldo")
    )["run_id"]
    claimed = pg_store.claim(30)
    assert claimed
    pg_store.cancel(tid, rid, actor)
    pg_store.delete_thread(tid, actor)
    with pg_store.execution_guard(claimed[0]) as allowed:
        assert not allowed
    with pytest.raises(StoreError) as error:
        pg_store.create_run(
            tid, CreateRun(actor_user_id=90001, client_request_id=uuid4(), message="Late")
        )
    assert error.value.status == 404


async def test_worker_holds_guard_until_graph_cancellation_finishes(
    pg_store: Store, tools, monkeypatch
) -> None:
    import asyncio
    from contextlib import asynccontextmanager

    from finance_agent import worker

    actor = Actor(actor_user_id=90001)
    tid = pg_store.create_thread(CreateThread(actor_user_id=90001, client_request_id=uuid4()))["id"]
    rid = pg_store.create_run(
        tid, CreateRun(actor_user_id=90001, client_request_id=uuid4(), message="Saldo")
    )["run_id"]
    claimed = pg_store.claim(30)
    assert claimed
    started, cancelling, release = asyncio.Event(), asyncio.Event(), asyncio.Event()

    @asynccontextmanager
    async def connector(*args):
        yield tools

    async def graph(*args, **kwargs):
        started.set()
        try:
            await asyncio.Event().wait()
        except asyncio.CancelledError:
            cancelling.set()
            await release.wait()
            raise

    monkeypatch.setattr(worker, "connect_tools", connector)
    monkeypatch.setattr(worker, "ChatReasoner", lambda _: None)
    monkeypatch.setattr(worker, "run_graph", graph)
    task = asyncio.create_task(worker.execute(pg_store, Settings(), claimed[0], actor, None))
    try:
        await asyncio.wait_for(started.wait(), timeout=2)
        pg_store.cancel(tid, rid, actor)
        task.cancel()
        await asyncio.wait_for(cancelling.wait(), timeout=2)
        with pytest.raises(StoreError) as error:
            pg_store.delete_thread(tid, actor)
        assert error.value.code == "thread_busy"
    finally:
        release.set()
        with pytest.raises(asyncio.CancelledError):
            await task
    pg_store.delete_thread(tid, actor)
    tools.calls.clear()
    await worker.execute(pg_store, Settings(), claimed[0], actor, None)
    assert tools.calls == []


async def test_competing_worker_waits_and_completes_after_previous_releases(
    pg_store: Store, tools, monkeypatch
) -> None:
    import asyncio
    from contextlib import asynccontextmanager

    from finance_agent import worker
    from finance_agent.graph import GraphResult

    actor = Actor(actor_user_id=90001)
    tid = pg_store.create_thread(CreateThread(actor_user_id=90001, client_request_id=uuid4()))["id"]
    first = pg_store.create_run(
        tid, CreateRun(actor_user_id=90001, client_request_id=uuid4(), message="Primero")
    )["run_id"]
    first_claimed = pg_store.claim(30)
    assert first_claimed

    @asynccontextmanager
    async def connector(*args):
        yield tools

    async def graph(*args, **kwargs):
        return GraphResult(content="Completado", references=[], warnings=[])

    monkeypatch.setattr(worker, "connect_tools", connector)
    monkeypatch.setattr(worker, "ChatReasoner", lambda _: None)
    monkeypatch.setattr(worker, "run_graph", graph)
    with pg_store.execution_guard(first_claimed[0]) as allowed:
        assert allowed
        pg_store.cancel(tid, first, actor)
        second = pg_store.create_run(
            tid, CreateRun(actor_user_id=90001, client_request_id=uuid4(), message="Segundo")
        )["run_id"]
        second_claimed = pg_store.claim(30)
        assert second_claimed
        operation = worker.execute(pg_store, Settings(), second_claimed[0], actor, None)
        task = asyncio.create_task(
            worker.supervise(pg_store, Settings(), second_claimed[0], operation)
        )
        await asyncio.sleep(0.05)
        assert not task.done()
        assert pg_store.get_run(tid, second, actor)["status"] == "running"
    await asyncio.wait_for(task, timeout=3)
    assert pg_store.get_run(tid, second, actor)["status"] == "completed"
    pg_store.delete_thread(tid, actor)
