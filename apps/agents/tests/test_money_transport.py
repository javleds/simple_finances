import json
from unittest.mock import AsyncMock

import pytest
from mcp import ClientSession
from mcp.types import CallToolResult, TextContent

from finance_agent.mcp_client import McpTools


@pytest.mark.parametrize("structured", [True, False])
async def test_money_strings_survive_structured_and_text_tool_results(structured: bool) -> None:
    payload = {
        "schema_version": 1,
        "data": {
            "balance": "90071992547409.91",
            "amount": "-0.01",
            "available_balance": None,
            "items": [{"amount": "0.00"}],
        },
    }
    session = AsyncMock(spec=ClientSession)
    session.call_tool.return_value = CallToolResult(
        content=[TextContent(type="text", text=json.dumps(payload))],
        structuredContent=payload if structured else None,
    )
    result = await McpTools(session).call("get_account_balance", {"account_id": 1})
    assert result == payload
    assert isinstance(result["data"]["balance"], str)
    assert json.loads(json.dumps(result)) == payload
