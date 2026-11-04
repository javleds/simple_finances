from typing import Any

from langchain_core.messages import AIMessage, BaseMessage, SystemMessage, convert_to_messages

from finance_agent.graph import SYSTEM_PROMPT, Answer, ChatReasoner, build_graph
from finance_agent.mcp_client import connect_tools
from finance_agent.settings import Settings


class StudioTools:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings

    def actor(self) -> int:
        actor = self.settings.agent_dev_actor_user_id
        if actor is None:
            raise ValueError("Set AGENT_DEV_ACTOR_USER_ID to execute Studio queries")
        return actor

    async def catalog(self) -> list[dict[str, Any]]:
        async with connect_tools(self.settings, self.actor()) as tools:
            return await tools.catalog()

    async def call(self, name: str, arguments: dict[str, Any]) -> dict[str, Any]:
        async with connect_tools(self.settings, self.actor()) as tools:
            return await tools.call(name, arguments)


class StudioReasoner:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings

    def messages(self, messages: list[BaseMessage]) -> list[BaseMessage]:
        return [SystemMessage(content=SYSTEM_PROMPT), *convert_to_messages(messages)]

    async def next(self, messages: list[BaseMessage], catalog: list[dict[str, Any]]) -> AIMessage:
        return await ChatReasoner(self.settings).next(self.messages(messages), catalog)

    async def answer(self, messages: list[BaseMessage]) -> Answer:
        return await ChatReasoner(self.settings).answer(self.messages(messages))


settings = Settings()
graph = build_graph(settings, StudioTools(settings), StudioReasoner(settings))
