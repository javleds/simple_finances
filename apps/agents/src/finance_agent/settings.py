from functools import lru_cache
from zoneinfo import ZoneInfo

from pydantic import Field, SecretStr, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")
    database_url: str = "postgresql+psycopg://agent:agent@agent-postgres:5432/agent"
    finance_agent_service_token: SecretStr = SecretStr("")
    agent_mcp_service_token: SecretStr = SecretStr("")
    mcp_url: str = "http://agent-mcp/mcp"
    openai_api_key: SecretStr = SecretStr("")
    agent_model: str = "gpt-4.1-mini"
    agent_model_base_url: str | None = None
    agent_timezone: str = "America/Mexico_City"
    agent_run_timeout: int = Field(default=120, ge=10, le=600)
    agent_tool_limit: int = Field(default=12, ge=1, le=30)
    agent_detail_limit: int = Field(default=1000, ge=100, le=1000)
    agent_lease_seconds: int = Field(default=30, ge=10, le=120)
    agent_retention_days: int = Field(default=30, ge=1, le=365)
    agent_history_limit: int = Field(default=20, ge=2, le=40)
    agent_max_output_tokens: int = Field(default=2000, ge=100, le=4000)

    @field_validator("agent_timezone")
    @classmethod
    def valid_timezone(cls, value: str) -> str:
        ZoneInfo(value)
        return value

    @property
    def checkpoint_url(self) -> str:
        return self.database_url.replace("postgresql+psycopg://", "postgresql://", 1)


@lru_cache
def get_settings() -> Settings:
    return Settings()
