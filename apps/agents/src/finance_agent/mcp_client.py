import json
from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from typing import Any, Protocol

import httpx
from mcp import ClientSession
from mcp.client.streamable_http import streamablehttp_client

from finance_agent.settings import Settings

TOOL_NAMES = frozenset(
    {
        "find_accounts",
        "list_account_balances",
        "get_account_balance",
        "get_payables_summary",
        "get_payable_breakdown",
        "find_categories",
        "summarize_expenses",
        "list_expenses",
        "aggregate_expense_groups",
    }
)


class ToolDenied(Exception):
    pass


class ToolValidation(Exception):
    pass


class ToolUnavailable(Exception):
    pass


class FinancialTools(Protocol):
    async def catalog(self) -> list[dict[str, Any]]: ...
    async def call(self, name: str, arguments: dict[str, Any]) -> dict[str, Any]: ...


class McpTools:
    def __init__(self, session: ClientSession) -> None:
        self.session = session

    async def catalog(self) -> list[dict[str, Any]]:
        result = await self.session.list_tools()
        tools = []
        for tool in result.tools:
            if tool.name not in TOOL_NAMES:
                continue
            properties = tool.inputSchema.get("properties", {})
            if {"actor_user_id", "user_id"} & properties.keys():
                raise ToolUnavailable("Unsafe tool identity schema")
            tools.append(
                {
                    "type": "function",
                    "function": {
                        "name": tool.name,
                        "description": tool.description or tool.name,
                        "parameters": tool.inputSchema,
                    },
                }
            )
        if not tools:
            raise ToolUnavailable("No financial tools available")
        return tools

    async def call(self, name: str, arguments: dict[str, Any]) -> dict[str, Any]:
        if name not in TOOL_NAMES or {"user_id", "actor_user_id"} & arguments.keys():
            raise ToolDenied("Invalid tool arguments")
        result = await self.session.call_tool(name, arguments)
        structured = result.structuredContent
        if structured is None:
            for content in result.content:
                if content.type == "text":
                    try:
                        parsed = json.loads(content.text)
                    except json.JSONDecodeError:
                        continue
                    if isinstance(parsed, dict):
                        structured = parsed
                        break
        if result.isError:
            error = (structured or {}).get("error", {})
            code = error.get("code") if isinstance(error, dict) else error
            if code in {"forbidden", "not_found", "unauthorized", "account_not_found"}:
                raise ToolDenied("Financial resource is not accessible")
            if code == "validation_error":
                raise ToolValidation("Invalid financial query filters")
            raise ToolUnavailable("Financial query failed")
        if not structured or structured.get("schema_version") != 1:
            raise ToolUnavailable("Invalid financial tool result")
        return structured


@asynccontextmanager
async def connect_tools(settings: Settings, actor_user_id: int) -> AsyncIterator[FinancialTools]:
    token = settings.agent_mcp_service_token.get_secret_value()
    if not token:
        raise ToolUnavailable("MCP credential is not configured")
    headers = {"Authorization": f"Bearer {token}", "X-Actor-User-Id": str(actor_user_id)}
    try:
        async with streamablehttp_client(
            settings.mcp_url,
            headers=headers,
            timeout=15,
            sse_read_timeout=settings.agent_run_timeout,
        ) as streams:
            async with ClientSession(streams[0], streams[1]) as session:
                await session.initialize()
                yield McpTools(session)
    except httpx.HTTPStatusError as error:
        if error.response.status_code == 403:
            raise ToolDenied("Actor access denied") from None
        raise
    except BaseExceptionGroup as error:
        original: BaseException = error
        while isinstance(original, BaseExceptionGroup) and len(original.exceptions) == 1:
            original = original.exceptions[0]
        if isinstance(original, httpx.HTTPStatusError) and original.response.status_code == 403:
            raise ToolDenied("Actor access denied") from None
        raise original from None


async def visible_messages(
    tools: FinancialTools, messages: list[dict[str, Any]]
) -> list[dict[str, Any]]:
    visible: dict[str, bool] = {}
    result = []
    for message in messages:
        refs = list(message.get("references", []))
        account_id = message.get("context", {}).get("account_id")
        if account_id and "conversation_context" not in message.get("context", {}):
            refs.append({"account_id": str(account_id)})
        allowed = True
        for ref in refs:
            account_id = str(ref["account_id"])
            if account_id not in visible:
                try:
                    await tools.call("get_account_balance", {"account_id": int(account_id)})
                    visible[account_id] = True
                except ToolDenied:
                    visible[account_id] = False
            if not visible[account_id]:
                allowed = False
        if allowed:
            result.append(message)
    return result
