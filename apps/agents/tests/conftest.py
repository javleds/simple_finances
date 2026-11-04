from collections.abc import AsyncIterator, Iterator
from contextlib import asynccontextmanager
from typing import Any

import pytest
from fastapi.testclient import TestClient
from pydantic import SecretStr
from sqlalchemy import create_engine, event
from sqlalchemy.pool import StaticPool

from finance_agent.api import create_app
from finance_agent.mcp_client import ToolDenied
from finance_agent.models import Base
from finance_agent.settings import Settings
from finance_agent.store import Store


class FakeTools:
    def __init__(self) -> None:
        self.denied: set[int] = set()
        self.calls: list[tuple[str, dict[str, Any]]] = []

    async def catalog(self) -> list[dict[str, Any]]:
        return [
            {
                "type": "function",
                "function": {
                    "name": "get_account_balance",
                    "parameters": {
                        "type": "object",
                        "properties": {"account_id": {"type": "integer"}},
                    },
                },
            }
        ]

    async def call(self, name: str, arguments: dict[str, Any]) -> dict[str, Any]:
        self.calls.append((name, arguments))
        if int(arguments.get("account_id", 0)) in self.denied:
            raise ToolDenied()
        return {
            "schema_version": 1,
            "data": {"balance": "125.50"},
            "complete": True,
            "references": [{"account_id": str(arguments.get("account_id", 42))}],
        }


@pytest.fixture
def store() -> Iterator[Store]:
    engine = create_engine(
        "sqlite://", connect_args={"check_same_thread": False}, poolclass=StaticPool
    )

    @event.listens_for(engine, "connect")
    def enable_foreign_keys(connection: Any, _: Any) -> None:
        connection.execute("PRAGMA foreign_keys=ON")

    Base.metadata.create_all(engine)
    yield Store(engine)
    engine.dispose()


@pytest.fixture
def tools() -> FakeTools:
    return FakeTools()


@pytest.fixture
def client(store: Store, tools: FakeTools) -> Iterator[TestClient]:
    settings = Settings(finance_agent_service_token=SecretStr("test-service"))
    app = create_app(settings, store)

    @asynccontextmanager
    async def connector(_: Settings, actor: int) -> AsyncIterator[FakeTools]:
        assert actor > 0
        yield tools

    app.state.connect_tools = connector
    with TestClient(app, headers={"Authorization": "Bearer test-service"}) as test_client:
        yield test_client
