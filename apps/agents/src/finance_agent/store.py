import hashlib
import json
from collections.abc import Iterator
from contextlib import contextmanager
from datetime import UTC, datetime, timedelta
from typing import Any, Literal, cast

from sqlalchemy import Engine, create_engine, select, text
from sqlalchemy.orm import Session, sessionmaker

from finance_agent.conversation_context import ConversationContext
from finance_agent.models import Event, Message, Run, Thread, now
from finance_agent.schemas import Actor, CreateRun, CreateThread
from finance_agent.titles import valid_title

TERMINAL = {"completed", "failed", "cancelled"}


class StoreError(Exception):
    def __init__(self, code: str, status: int) -> None:
        self.code = code
        self.status = status
        super().__init__(code)


def make_engine(url: str) -> Engine:
    return create_engine(url, pool_pre_ping=True)


def message_data(message: Message) -> dict[str, Any]:
    return {
        "id": message.id,
        "role": message.role,
        **(
            {"conversation_context": message.context["conversation_context"]}
            if "conversation_context" in message.context
            else {}
        ),
        "content": message.content,
        "context": message.context,
        "references": message.references,
        "warnings": message.warnings,
        "created_at": message.created_at.replace(tzinfo=UTC).isoformat(),
    }


def thread_data(thread: Thread) -> dict[str, Any]:
    return {
        "id": thread.id,
        "channel": thread.channel,
        "title": thread.title,
        "title_references": thread.title_references,
        "created_at": thread.created_at.replace(tzinfo=UTC).isoformat(),
        "updated_at": thread.updated_at.replace(tzinfo=UTC).isoformat(),
    }


class Store:
    def __init__(self, engine: Engine) -> None:
        self.engine = engine
        self.sessions = sessionmaker(engine, expire_on_commit=False)

    def owned_thread(
        self, session: Session, thread_id: str, actor: Actor, lock: bool = False
    ) -> Thread:
        query = select(Thread).where(
            Thread.id == thread_id,
            Thread.owner_user_id == actor.actor_user_id,
            Thread.channel == actor.channel,
        )
        if lock:
            query = query.with_for_update().execution_options(populate_existing=True)
        thread = session.scalar(query)
        if thread is None:
            raise StoreError("not_found", 404)
        return thread

    def owned_run(self, session: Session, thread_id: str, run_id: str, actor: Actor) -> Run:
        self.owned_thread(session, thread_id, actor)
        run = session.scalar(
            select(Run)
            .where(
                Run.id == run_id,
                Run.thread_id == thread_id,
            )
            .with_for_update()
        )
        if run is None:
            raise StoreError("not_found", 404)
        return run

    def create_thread(self, request: CreateThread) -> dict[str, Any]:
        from sqlalchemy.dialects.postgresql import insert as pg_insert
        from sqlalchemy.dialects.sqlite import insert as sqlite_insert

        insert = pg_insert if self.engine.dialect.name == "postgresql" else sqlite_insert
        with self.sessions.begin() as session:
            session.execute(
                insert(Thread)
                .values(
                    owner_user_id=request.actor_user_id,
                    channel=request.channel,
                    request_id=str(request.client_request_id),
                )
                .on_conflict_do_nothing(index_elements=["owner_user_id", "channel", "request_id"])
            )
            thread = session.scalar(
                select(Thread).where(
                    Thread.owner_user_id == request.actor_user_id,
                    Thread.channel == request.channel,
                    Thread.request_id == str(request.client_request_id),
                )
            )
            assert thread is not None
            return thread_data(thread)

    def list_threads(self, actor: Actor, cursor: str | None) -> dict[str, Any]:
        with self.sessions() as session:
            query = (
                select(Thread)
                .where(
                    Thread.owner_user_id == actor.actor_user_id,
                    Thread.channel == actor.channel,
                )
                .order_by(Thread.created_at.desc(), Thread.id.desc())
            )
            if cursor:
                previous = self.owned_thread(session, cursor, actor)
                query = query.where(
                    (Thread.created_at < previous.created_at)
                    | ((Thread.created_at == previous.created_at) & (Thread.id < previous.id))
                )
            rows = list(session.scalars(query.limit(31)))
            return {
                "items": [thread_data(row) for row in rows[:30]],
                "next_cursor": rows[29].id if len(rows) > 30 else None,
            }

    def conversation_context(self, thread_id: str, actor: Actor) -> ConversationContext:
        with self.sessions() as session:
            thread = self.owned_thread(session, thread_id, actor)
            return ConversationContext.model_validate(thread.conversation_context or {})

    def needs_title(self, thread_id: str, actor: Actor) -> bool:
        with self.sessions() as session:
            return self.owned_thread(session, thread_id, actor).title is None

    def create_run(self, thread_id: str, request: CreateRun) -> dict[str, Any]:
        digest = hashlib.sha256(
            json.dumps(
                {
                    "message": request.message,
                    "context": request.context.model_dump(),
                    "channel": request.channel,
                },
                sort_keys=True,
            ).encode()
        ).hexdigest()
        with self.sessions.begin() as session:
            thread = self.owned_thread(session, thread_id, request, lock=True)
            existing = session.scalar(
                select(Run).where(
                    Run.thread_id == thread_id,
                    Run.request_id == str(request.client_request_id),
                )
            )
            if existing:
                if existing.input_hash != digest:
                    raise StoreError("idempotency_conflict", 409)
                return self.run_data(session, existing)
            active = session.scalar(
                select(Run.id).where(
                    Run.thread_id == thread_id,
                    Run.status.in_(["queued", "running"]),
                )
            )
            if active:
                raise StoreError("run_active", 409)
            run = Run(
                thread_id=thread_id,
                request_id=str(request.client_request_id),
                input_hash=digest,
                input_text=request.message,
                context=request.context.model_dump(),
                status="queued",
            )
            session.add(run)
            session.flush()
            session.add(
                Message(
                    thread_id=thread_id,
                    run_id=run.id,
                    role="user",
                    content=request.message,
                    context=run.context,
                    references=self.context_references(run.context),
                )
            )
            thread.updated_at = now()
            return self.run_data(session, run)

    @staticmethod
    def context_references(context: dict[str, Any]) -> list[dict[str, str]]:
        account_id = context.get("account_id")
        return [{"account_id": str(account_id)}] if account_id else []

    def run_data(self, session: Session, run: Run) -> dict[str, Any]:
        message = session.scalar(
            select(Message).where(
                Message.run_id == run.id,
                Message.role == "assistant",
            )
        )
        return {
            "thread_id": run.thread_id,
            "run_id": run.id,
            "status": run.status,
            "error": run.error,
            "message": message_data(message) if message else None,
        }

    def get_run(self, thread_id: str, run_id: str, actor: Actor) -> dict[str, Any]:
        with self.sessions.begin() as session:
            return self.run_data(session, self.owned_run(session, thread_id, run_id, actor))

    def history(
        self, thread_id: str, actor: Actor, cursor: str | None = None, limit: int = 100
    ) -> dict[str, Any]:
        with self.sessions.begin() as session:
            self.owned_thread(session, thread_id, actor)
            query = (
                select(Message)
                .where(Message.thread_id == thread_id)
                .order_by(
                    Message.created_at.desc(),
                    Message.id.desc(),
                )
            )
            if cursor:
                previous = session.get(Message, cursor)
                if previous is None or previous.thread_id != thread_id:
                    raise StoreError("invalid_cursor", 422)
                query = query.where(
                    (Message.created_at < previous.created_at)
                    | ((Message.created_at == previous.created_at) & (Message.id < previous.id))
                )
            rows = list(session.scalars(query.limit(limit + 1)))
            active = session.scalar(
                select(Run).where(
                    Run.thread_id == thread_id,
                    Run.status.in_(["queued", "running"]),
                )
            )
            return {
                "items": [message_data(row) for row in reversed(rows[:limit])],
                "next_cursor": rows[limit - 1].id if len(rows) > limit else None,
                "active_run": self.run_data(session, active) if active else None,
            }

    def recent_history(self, thread_id: str, run_id: str, limit: int) -> list[dict[str, Any]]:
        with self.sessions() as session:
            rows = list(
                session.scalars(
                    select(Message)
                    .where(
                        Message.thread_id == thread_id,
                        Message.run_id != run_id,
                    )
                    .order_by(Message.created_at.desc(), Message.id.desc())
                    .limit(limit)
                )
            )
            return [message_data(row) for row in reversed(rows)]

    def emit(self, session: Session, run: Run, kind: str, payload: dict[str, Any]) -> None:
        run.sequence += 1
        session.add(Event(run_id=run.id, sequence=run.sequence, type=kind, payload=payload))

    def events(
        self, thread_id: str, run_id: str, actor: Actor, after: int
    ) -> tuple[list[dict[str, Any]], bool]:
        with self.sessions.begin() as session:
            run = self.owned_run(session, thread_id, run_id, actor)
            rows = session.scalars(
                select(Event)
                .where(
                    Event.run_id == run_id,
                    Event.sequence > after,
                )
                .order_by(Event.sequence)
                .limit(100)
            )
            events = [
                {
                    "schema_version": 1,
                    "thread_id": thread_id,
                    "run_id": run_id,
                    "sequence": event.sequence,
                    "type": event.type,
                    "payload": event.payload,
                }
                for event in rows
            ]
            return events, run.status in TERMINAL

    def cancel(self, thread_id: str, run_id: str, actor: Actor) -> dict[str, Any]:
        with self.sessions.begin() as session:
            run = self.owned_run(session, thread_id, run_id, actor)
            if run.status not in TERMINAL:
                run.status = "cancelled"
                run.finished_at = now()
                self.emit(session, run, "run.cancelled", {})
            return self.run_data(session, run)

    def claim(self, lease_seconds: int) -> tuple[Run, Actor] | None:
        with self.sessions.begin() as session:
            expired = session.scalars(
                select(Run)
                .where(
                    Run.status == "running",
                    Run.lease_until < now(),
                )
                .with_for_update(skip_locked=True)
            )
            for expired_run in expired:
                expired_run.status = "failed"
                expired_run.error = "worker_interrupted"
                expired_run.finished_at = now()
                self.emit(session, expired_run, "run.failed", {"code": "worker_interrupted"})
            run = session.scalar(
                select(Run)
                .where(Run.status == "queued")
                .order_by(
                    Run.created_at,
                )
                .with_for_update(skip_locked=True)
                .limit(1)
            )
            if run is None:
                return None
            thread = session.get(Thread, run.thread_id)
            assert thread is not None
            run.status = "running"
            run.lease_until = now() + timedelta(seconds=lease_seconds)
            self.emit(session, run, "run.started", {})
            self.emit(session, run, "status", {"text": "Consultando información financiera"})
            return run, Actor(
                actor_user_id=thread.owner_user_id,
                channel=cast(Literal["web", "whatsapp"], thread.channel),
            )

    def heartbeat(self, run_id: str, lease_seconds: int) -> bool:
        with self.sessions.begin() as session:
            run = session.scalar(select(Run).where(Run.id == run_id).with_for_update())
            if run is None or run.status != "running":
                return False
            run.lease_until = now() + timedelta(seconds=lease_seconds)
            return True

    def finish(
        self,
        run_id: str,
        content: str | None = None,
        references: list[dict[str, str]] | None = None,
        warnings: list[str] | None = None,
        error: str | None = None,
        conversation_title: str | None = None,
        conversation_context: ConversationContext | None = None,
    ) -> bool:
        with self.sessions.begin() as session:
            run = session.scalar(select(Run).where(Run.id == run_id).with_for_update())
            if run is None or run.status != "running":
                return False
            run.finished_at = now()
            if error:
                run.status = "failed"
                run.error = error
                self.emit(session, run, "run.failed", {"code": error})
                return True
            refs = list(references or [])
            if conversation_context:
                for reference in conversation_context.references():
                    if reference not in refs:
                        refs.append(reference)
            user_message = session.scalar(
                select(Message).where(
                    Message.run_id == run_id,
                    Message.role == "user",
                )
            )
            if user_message:
                user_message.references = refs + (
                    [] if conversation_context else self.context_references(run.context)
                )
                if conversation_context is not None:
                    user_message.context = {
                        **run.context,
                        "conversation_context": conversation_context.public(),
                    }
            message = Message(
                thread_id=run.thread_id,
                run_id=run.id,
                role="assistant",
                content=content or "",
                context={
                    **run.context,
                    **(
                        {"conversation_context": conversation_context.public()}
                        if conversation_context
                        else {}
                    ),
                },
                references=refs,
                warnings=warnings or [],
            )
            session.add(message)
            session.flush()
            run.status = "completed"
            self.emit(session, run, "message.completed", message_data(message))
            self.emit(session, run, "run.completed", {})
            thread = session.get(Thread, run.thread_id)
            assert thread is not None
            thread.updated_at = now()
            if conversation_context is not None:
                thread.conversation_context = conversation_context.model_dump()
            title = valid_title(conversation_title)
            if thread.title is None and title is not None:
                thread.title = title
                thread.title_references = refs + (
                    [] if conversation_context else self.context_references(run.context)
                )
            return True

    @staticmethod
    def lock_key(thread_id: str) -> int:
        return int.from_bytes(hashlib.sha256(thread_id.encode()).digest()[:8], signed=True)

    def acquire_thread_lock(self, session: Session, thread_id: str) -> bool:
        if self.engine.dialect.name != "postgresql":
            return True
        return bool(
            session.scalar(
                text("SELECT pg_try_advisory_xact_lock(:key)"),
                {"key": self.lock_key(thread_id)},
            )
        )

    @contextmanager
    def execution_guard(self, run: Run) -> Iterator[bool]:
        with self.sessions.begin() as session:
            if not self.acquire_thread_lock(session, run.thread_id):
                raise StoreError("thread_busy", 409)
            current = session.get(Run, run.id)
            yield current is not None and current.status == "running"

    def delete_thread(
        self, thread_id: str, actor: Actor, updated_before: datetime | None = None
    ) -> None:
        with self.sessions.begin() as session:
            self.owned_thread(session, thread_id, actor)
            if not self.acquire_thread_lock(session, thread_id):
                raise StoreError("thread_busy", 409)
            thread = self.owned_thread(session, thread_id, actor, lock=True)
            if (
                updated_before is not None
                and thread.updated_at.replace(tzinfo=UTC) >= updated_before
            ):
                raise StoreError("thread_recent", 409)
            active = session.scalar(
                select(Run.id).where(
                    Run.thread_id == thread_id,
                    Run.status.in_(["queued", "running"]),
                )
            )
            if active is not None:
                raise StoreError("run_active", 409)
            if self.engine.dialect.name == "postgresql":
                for table in ("checkpoint_writes", "checkpoint_blobs", "checkpoints"):
                    session.execute(
                        text(f"DELETE FROM {table} WHERE thread_id = :thread_id"),
                        {"thread_id": thread_id},
                    )
            session.delete(thread)

    def purge(self, retention_days: int) -> list[str]:
        cutoff = now() - timedelta(days=retention_days)
        with self.sessions() as session:
            candidates = [
                (
                    thread.id,
                    Actor(
                        actor_user_id=thread.owner_user_id,
                        channel=cast(Literal["web", "whatsapp"], thread.channel),
                    ),
                )
                for thread in session.scalars(
                    select(Thread).where(
                        Thread.updated_at < cutoff,
                    )
                )
            ]
        deleted = []
        for thread_id, actor in candidates:
            try:
                self.delete_thread(thread_id, actor, updated_before=cutoff)
            except StoreError as error:
                if error.status not in {404, 409}:
                    raise
                continue
            deleted.append(thread_id)
        return deleted
