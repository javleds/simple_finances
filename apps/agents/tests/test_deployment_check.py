from typing import Any

import httpx
import pytest
from sqlalchemy import create_engine

from finance_agent import deployment_check
from finance_agent.models import Base
from finance_agent.settings import Settings


@pytest.mark.parametrize(
    "schema_exists,mcp_ok,expected", [(True, True, 0), (False, True, 1), (True, False, 1)]
)
def test_deployment_checks_schema_and_mcp_without_model(
    monkeypatch: pytest.MonkeyPatch, capsys: Any, schema_exists: bool, mcp_ok: bool, expected: int
) -> None:
    engine = create_engine("sqlite://")
    if schema_exists:
        Base.metadata.create_all(engine)
    monkeypatch.setattr(deployment_check, "make_engine", lambda _: engine)
    monkeypatch.setattr(
        deployment_check,
        "get_settings",
        lambda: Settings(_env_file=None, mcp_url="http://agent-mcp:8080/mcp"),
    )

    def get(self: httpx.Client, url: str) -> httpx.Response:
        assert url == "http://agent-mcp:8080/up"
        if not mcp_ok:
            raise RuntimeError("secret-that-must-not-be-logged")
        return httpx.Response(200, request=httpx.Request("GET", url))

    monkeypatch.setattr(httpx.Client, "get", get)
    assert deployment_check.main() == expected
    assert "secret-that-must-not-be-logged" not in capsys.readouterr().err
