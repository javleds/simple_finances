from typing import Any
from uuid import uuid4

from langchain_core.messages import AIMessage, BaseMessage

from finance_agent.graph import Answer, run_graph
from finance_agent.settings import Settings


class FakeModel:
    def __init__(self, tool: str | None = "get_account_balance") -> None:
        self.tool = tool
        self.calls = 0
        self.seen: list[BaseMessage] = []

    async def next(self, messages: list[BaseMessage], catalog: list[dict[str, Any]]) -> AIMessage:
        self.calls += 1
        self.seen = messages
        if self.tool and self.calls == 1:
            return AIMessage(
                content="",
                tool_calls=[
                    {
                        "id": "one",
                        "name": self.tool,
                        "args": {"account_id": 42},
                    }
                ],
            )
        return AIMessage(content="ready")

    async def answer(self, messages: list[BaseMessage]) -> Answer:
        return Answer(content="El balance es $125.50 MXN.", scope="account")


async def test_graph_uses_tool_evidence_and_structured_answer(tools: Any) -> None:
    result = await run_graph(
        Settings(),
        tools,
        FakeModel(),
        str(uuid4()),
        str(uuid4()),
        "Balance de esta cuenta",
        {"scope": "account", "account_id": "42"},
        [],
    )
    assert result.content == "El balance es $125.50 MXN."
    assert result.references == [{"account_id": "42"}]
    assert tools.calls == [("get_account_balance", {"account_id": 42})]


async def test_no_tool_followup_inherits_all_history_evidence(tools: Any) -> None:
    history = [
        {
            "role": "assistant",
            "content": "Saldo $125.50",
            "context": {"scope": "global"},
            "references": [{"account_id": "42"}, {"account_id": "42", "transaction_id": "9"}],
        }
    ]
    result = await run_graph(
        Settings(),
        tools,
        FakeModel(None),
        str(uuid4()),
        str(uuid4()),
        "Repite el saldo",
        {"scope": "global"},
        history,
    )
    assert result.references == [{"account_id": "42"}]


async def test_tool_limit_stops_loop_and_marks_partial(tools: Any) -> None:
    class LoopModel(FakeModel):
        async def next(
            self, messages: list[BaseMessage], catalog: list[dict[str, Any]]
        ) -> AIMessage:
            return AIMessage(
                content="",
                tool_calls=[
                    {"id": "a", "name": "get_account_balance", "args": {"account_id": 42}},
                    {"id": "b", "name": "get_account_balance", "args": {"account_id": 42}},
                ],
            )

    result = await run_graph(
        Settings(agent_tool_limit=1),
        tools,
        LoopModel(),
        str(uuid4()),
        str(uuid4()),
        "Saldo",
        {"scope": "global"},
        [],
    )
    assert len(tools.calls) == 1
    assert "tool_limit_reached" in result.warnings
