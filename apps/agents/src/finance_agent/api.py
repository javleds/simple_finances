import asyncio
import hmac
import json
from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from typing import Annotated, Any
from uuid import UUID

from fastapi import Depends, FastAPI, Header, HTTPException, Query, Request
from fastapi.responses import JSONResponse, StreamingResponse
from sqlalchemy import text
from sqlalchemy.exc import SQLAlchemyError

from finance_agent.mcp_client import ToolDenied, connect_tools, visible_messages
from finance_agent.schemas import Actor, CreateRun, CreateThread
from finance_agent.settings import Settings, get_settings
from finance_agent.store import Store, StoreError, make_engine


def create_app(settings: Settings | None = None, store: Store | None = None) -> FastAPI:
    config = settings or get_settings()
    repository = store or Store(make_engine(config.database_url))

    @asynccontextmanager
    async def lifespan(_: FastAPI) -> AsyncIterator[None]:
        yield
        repository.engine.dispose()

    app = FastAPI(
        title="Fin-si internal agent",
        docs_url=None,
        redoc_url=None,
        openapi_url=None,
        lifespan=lifespan,
    )
    app.state.store = repository
    app.state.settings = config
    app.state.connect_tools = connect_tools

    @app.exception_handler(StoreError)
    async def store_error(_: Request, error: StoreError) -> JSONResponse:
        return JSONResponse({"detail": error.code}, status_code=error.status)

    @app.exception_handler(SQLAlchemyError)
    async def database_error(_: Request, error: SQLAlchemyError) -> JSONResponse:
        return JSONResponse({"detail": "state_service_unavailable"}, status_code=503)

    async def authenticated(authorization: Annotated[str | None, Header()] = None) -> None:
        expected = config.finance_agent_service_token.get_secret_value()
        if (
            not expected
            or authorization is None
            or not hmac.compare_digest(
                authorization.encode(),
                f"Bearer {expected}".encode(),
            )
        ):
            raise HTTPException(401, "unauthorized")

    auth = [Depends(authenticated)]

    def query_actor(actor_user_id: Annotated[int, Query(gt=0)], channel: str = "web") -> Actor:
        if channel not in {"web", "whatsapp"}:
            raise HTTPException(422, "invalid_channel")
        return Actor(actor_user_id=actor_user_id, channel=channel)  # type: ignore[arg-type]

    @asynccontextmanager
    async def authorized_tools(actor: Actor) -> AsyncIterator[Any]:
        try:
            async with app.state.connect_tools(config, actor.actor_user_id) as tools:
                await tools.call("list_account_balances", {"limit": 1})
                yield tools
        except (HTTPException, StoreError):
            raise
        except ToolDenied:
            raise HTTPException(403, "access_denied") from None
        except Exception:
            raise HTTPException(503, "financial_service_unavailable") from None

    @app.get("/health/live")
    async def live() -> dict[str, str]:
        return {"status": "ok"}

    @app.get("/health/ready")
    async def ready() -> dict[str, str]:
        try:
            with repository.engine.connect() as connection:
                connection.execute(text("SELECT 1 FROM agent_threads LIMIT 1"))
        except SQLAlchemyError:
            raise HTTPException(503, "not_ready") from None
        if not config.finance_agent_service_token.get_secret_value():
            raise HTTPException(503, "not_ready")
        return {"status": "ok"}

    @app.post("/internal/v1/threads", dependencies=auth, status_code=201)
    async def create_thread(body: CreateThread) -> dict[str, Any]:
        async with authorized_tools(body):
            return repository.create_thread(body)

    @app.get("/internal/v1/threads", dependencies=auth)
    async def threads(
        actor: Annotated[Actor, Depends(query_actor)], cursor: UUID | None = None
    ) -> dict[str, Any]:
        async with authorized_tools(actor):
            return repository.list_threads(actor, str(cursor) if cursor else None)

    @app.get("/internal/v1/threads/{thread_id}/messages", dependencies=auth)
    async def messages(
        thread_id: UUID, actor: Annotated[Actor, Depends(query_actor)], cursor: UUID | None = None
    ) -> dict[str, Any]:
        result = repository.history(str(thread_id), actor, str(cursor) if cursor else None)
        async with authorized_tools(actor) as tools:
            result["items"] = await visible_messages(tools, result["items"])
        return result

    @app.post("/internal/v1/threads/{thread_id}/runs", dependencies=auth, status_code=202)
    async def start(thread_id: UUID, body: CreateRun) -> dict[str, Any]:
        with repository.sessions.begin() as session:
            repository.owned_thread(session, str(thread_id), body)
        async with authorized_tools(body) as tools:
            if body.context.account_id:
                await tools.call(
                    "get_account_balance", {"account_id": int(body.context.account_id)}
                )
            result = repository.create_run(str(thread_id), body)
            if result["message"] and not await visible_messages(tools, [result["message"]]):
                result["message"] = None
                result["error"] = "references_no_longer_visible"
            return result

    @app.get("/internal/v1/threads/{thread_id}/runs/{run_id}", dependencies=auth)
    async def run_status(
        thread_id: UUID, run_id: UUID, actor: Annotated[Actor, Depends(query_actor)]
    ) -> dict[str, Any]:
        result = repository.get_run(str(thread_id), str(run_id), actor)
        async with authorized_tools(actor) as tools:
            if result["message"] and not await visible_messages(tools, [result["message"]]):
                result["message"] = None
                result["error"] = "references_no_longer_visible"
        return result

    @app.post("/internal/v1/threads/{thread_id}/runs/{run_id}/cancel", dependencies=auth)
    async def cancel(thread_id: UUID, run_id: UUID, body: Actor) -> dict[str, Any]:
        async with authorized_tools(body):
            result = repository.cancel(str(thread_id), str(run_id), body)
            result["message"] = None
            return result

    @app.get("/internal/v1/threads/{thread_id}/runs/{run_id}/events", dependencies=auth)
    async def events(
        thread_id: UUID,
        run_id: UUID,
        actor: Annotated[Actor, Depends(query_actor)],
        after: Annotated[int, Query(ge=0)] = 0,
    ) -> StreamingResponse:
        repository.get_run(str(thread_id), str(run_id), actor)
        async with authorized_tools(actor):
            pass

        async def stream() -> AsyncIterator[str]:
            sequence = after
            idle = 0
            while True:
                rows, terminal = repository.events(str(thread_id), str(run_id), actor, sequence)
                for event in rows:
                    if event["type"] == "message.completed":
                        try:
                            async with authorized_tools(actor) as tools:
                                allowed = await visible_messages(tools, [event["payload"]])
                            if not allowed:
                                event["payload"] = {
                                    **event["payload"],
                                    "content": "",
                                    "references": [],
                                    "context": {"scope": "global"},
                                    "warnings": ["references_no_longer_visible"],
                                }
                        except HTTPException:
                            yield 'event: error\ndata: {"code":"access_unavailable"}\n\n'
                            return
                    sequence = event["sequence"]
                    yield (
                        f"id: {sequence}\nevent: {event['type']}\n"
                        f"data: {json.dumps(event, ensure_ascii=False)}\n\n"
                    )
                if terminal:
                    return
                idle += 1
                if idle % 15 == 0:
                    yield ": heartbeat\n\n"
                await asyncio.sleep(1)

        return StreamingResponse(
            stream(),
            media_type="text/event-stream",
            headers={
                "Cache-Control": "no-cache, no-transform",
                "X-Accel-Buffering": "no",
            },
        )

    return app


app = create_app()
