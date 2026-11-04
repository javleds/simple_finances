import asyncio
from datetime import timedelta
from typing import Protocol

from langgraph.checkpoint.postgres.aio import AsyncPostgresSaver
from sqlalchemy import select

from finance_agent.models import Run, Thread, now
from finance_agent.settings import get_settings
from finance_agent.store import Store, make_engine


class CheckpointCleaner(Protocol):
    async def adelete_thread(self, thread_id: str) -> None: ...


async def cleanup(store: Store, saver: CheckpointCleaner, retention_days: int) -> int:
    deleted = 0
    with store.sessions.begin() as session:
        threads = session.scalars(
            select(Thread)
            .where(
                Thread.updated_at < now() - timedelta(days=retention_days),
                ~select(Run.id)
                .where(
                    Run.thread_id == Thread.id,
                    Run.status.in_(["queued", "running"]),
                )
                .exists(),
            )
            .with_for_update(skip_locked=True)
        )
        for thread in threads:
            await saver.adelete_thread(thread.id)
            session.delete(thread)
            deleted += 1
    return deleted


async def main() -> None:
    settings = get_settings()
    store = Store(make_engine(settings.database_url))
    async with AsyncPostgresSaver.from_conn_string(settings.checkpoint_url) as saver:
        await cleanup(store, saver, settings.agent_retention_days)


if __name__ == "__main__":
    asyncio.run(main())
