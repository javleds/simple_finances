from typing import Annotated, Literal
from uuid import UUID

from pydantic import BaseModel, ConfigDict, Field, model_validator


class StrictModel(BaseModel):
    model_config = ConfigDict(extra="forbid")


class Actor(StrictModel):
    actor_user_id: Annotated[int, Field(strict=True, gt=0)]
    channel: Literal["web", "whatsapp"] = "web"


class Context(StrictModel):
    scope: Literal["global", "account"] = "global"
    account_id: Annotated[str, Field(pattern=r"^[1-9][0-9]{0,18}$")] | None = None
    route_name: Annotated[str, Field(max_length=120, pattern=r"^[a-zA-Z0-9_.-]+$")] | None = None

    @model_validator(mode="after")
    def matching_scope(self) -> "Context":
        if (self.scope == "account") != (self.account_id is not None):
            raise ValueError("Account context requires account_id; global context forbids it")
        return self


class CreateThread(Actor):
    client_request_id: UUID


class CreateRun(Actor):
    client_request_id: UUID
    message: Annotated[str, Field(min_length=1, max_length=4000)]
    context: Context = Field(default_factory=Context)

    @model_validator(mode="after")
    def valid_message(self) -> "CreateRun":
        if not self.message.strip():
            raise ValueError("Message cannot be blank")
        if self.channel == "whatsapp" and self.context.scope != "global":
            raise ValueError("WhatsApp requires global navigation context")
        return self
