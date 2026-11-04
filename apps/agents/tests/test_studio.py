import pytest
from pydantic import ValidationError

from finance_agent.settings import Settings
from finance_agent.studio import StudioTools, graph


def test_studio_exposes_real_graph_without_model_or_mcp_connection() -> None:
    topology = graph.get_graph()
    assert set(topology.nodes) == {"__start__", "interpret", "tools", "answer", "__end__"}
    assert ("tools", "interpret") in {(edge.source, edge.target) for edge in topology.edges}


async def test_studio_execution_requires_an_explicit_development_actor() -> None:
    tools = StudioTools(Settings(_env_file=None, agent_dev_actor_user_id=None))
    with pytest.raises(ValueError, match="AGENT_DEV_ACTOR_USER_ID"):
        await tools.catalog()


def test_studio_rejects_invalid_actor() -> None:
    with pytest.raises(ValidationError):
        Settings(_env_file=None, agent_dev_actor_user_id=0)
