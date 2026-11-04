import re
from typing import Any

from finance_agent.mcp_client import FinancialTools, visible_messages


def valid_title(value: Any) -> str | None:
    if not isinstance(value, str) or len(value) > 80:
        return None
    if not 3 <= len(value.split()) <= 8:
        return None
    if not re.fullmatch(r"[^\W\d_]+(?:[ -][^\W\d_]+)*", value):
        return None
    return value


async def visible_threads(
    tools: FinancialTools, threads: list[dict[str, Any]]
) -> list[dict[str, Any]]:
    candidates = [
        {"id": thread["id"], "references": thread["title_references"]}
        for thread in threads
        if thread.get("title")
    ]
    authorized = {item["id"] for item in await visible_messages(tools, candidates)}
    result = []
    for thread in threads:
        item = {key: value for key, value in thread.items() if key != "title_references"}
        if thread["id"] not in authorized:
            item["title"] = None
        result.append(item)
    return result
