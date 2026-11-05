import json

import httpx
import pytest
from langchain_core.messages import HumanMessage
from pydantic import SecretStr

from finance_agent.graph import ChatReasoner, ModelNotConfigured
from finance_agent.settings import Settings


@pytest.mark.parametrize("key,model", [("", "vendor/model"), ("test-key", "")])
def test_model_requires_explicit_openrouter_configuration(key: str, model: str) -> None:
    with pytest.raises(ModelNotConfigured):
        ChatReasoner(Settings(openrouter_api_key=SecretStr(key), agent_model=model))


async def test_openrouter_tool_call_and_structured_answer(monkeypatch: pytest.MonkeyPatch) -> None:
    requests: list[dict[str, object]] = []

    async def send(
        client: httpx.AsyncClient, request: httpx.Request, **kwargs: object
    ) -> httpx.Response:
        assert str(request.url) == "https://openrouter.ai/api/v1/chat/completions"
        assert request.headers["authorization"] == "Bearer test-key"
        payload = json.loads(request.content)
        requests.append(payload)
        message = (
            {
                "role": "assistant",
                "content": None,
                "tool_calls": [
                    {
                        "id": "call_balance",
                        "type": "function",
                        "function": {"name": "list_account_balances", "arguments": "{}"},
                    }
                ],
            }
            if len(requests) == 1
            else {"role": "assistant", "content": json.dumps({"content": "Tu saldo es $123.45."})}
        )
        return httpx.Response(
            200,
            request=request,
            json={
                "id": "test-completion",
                "object": "chat.completion",
                "created": 1,
                "system_fingerprint": None,
                "model": "vendor/model",
                "choices": [
                    {
                        "index": 0,
                        "message": message,
                        "finish_reason": "tool_calls" if len(requests) == 1 else "stop",
                    }
                ],
                "usage": {"prompt_tokens": 10, "completion_tokens": 10, "total_tokens": 20},
            },
        )

    monkeypatch.setattr(httpx.AsyncClient, "send", send)
    reasoner = ChatReasoner(
        Settings(
            openrouter_api_key=SecretStr("test-key"),
            agent_model="vendor/model",
        )
    )
    messages = [HumanMessage(content="¿Cuál es mi saldo?")]
    result = await reasoner.next(
        messages,
        [
            {
                "type": "function",
                "function": {
                    "name": "list_account_balances",
                    "description": "List balances",
                    "parameters": {"type": "object", "properties": {}},
                },
            }
        ],
    )
    assert result.tool_calls[0]["name"] == "list_account_balances"
    answer = await reasoner.answer(messages)
    assert answer.content == "Tu saldo es $123.45."
    assert requests[0]["model"] == "vendor/model"
    assert requests[0]["parallel_tool_calls"] is False
    assert requests[0]["max_tokens"] == 2000
    assert requests[1]["response_format"]["type"] == "json_schema"
    schema = requests[1]["response_format"]["json_schema"]
    assert schema["strict"] is True
    assert set(schema["schema"]["required"]) == set(schema["schema"]["properties"])
    assert schema["schema"]["additionalProperties"] is False
    assert reasoner.model.request_timeout == 120000
