from typing import Any
from uuid import uuid4

import pytest
from langchain_core.messages import BaseMessage

from finance_agent.conversation_context import (
    AccountSelection,
    ContextIntent,
    ConversationContext,
    ScopedTools,
    resolve_context,
    visible_context,
)
from finance_agent.mcp_client import ToolDenied, ToolUnavailable, ToolValidation
from finance_agent.schemas import Actor, CreateRun, CreateThread
from finance_agent.store import Store, StoreError


class ContextTools:
    def __init__(self) -> None:
        self.accounts = {"1": "Ahorro Javi", "2": "Ahorro Div", "3": "Diaria"}
        self.calls: list[tuple[str, dict[str, Any]]] = []
        self.incomplete = False

    async def catalog(self) -> list[dict[str, Any]]:
        return []

    async def call(self, name: str, args: dict[str, Any]) -> dict[str, Any]:
        self.calls.append((name, args))
        if name == "find_accounts":
            rows = [
                {"account_id": key, "name": value}
                for key, value in self.accounts.items()
                if args["name"].lower() in value.lower()
            ]
            return {"data": rows, "complete": not self.incomplete}
        if name == "get_account_balance":
            key = str(args["account_id"])
            if key not in self.accounts:
                raise ToolDenied()
            return {"data": {"account_id": key, "name": self.accounts[key]}}
        return {"data": []}


class IntentReasoner:
    def __init__(self, mode: str, **kwargs: Any) -> None:
        self.intent = ContextIntent.model_validate({"mode": mode, **kwargs})
        self.messages: list[BaseMessage] = []

    async def resolve(self, messages: list[BaseMessage]) -> ContextIntent:
        self.messages = messages
        return self.intent


async def test_selection_followup_and_navigation_remain_on_same_account() -> None:
    tools = ContextTools()
    first = await resolve_context(
        tools,
        IntentReasoner("select", account_queries=["Javi"]),
        ConversationContext(),
        {"scope": "global"},
        "Saldo de Javi",
        [],
    )
    assert first.state.active_account.account_id == "1"
    followup = await resolve_context(
        tools,
        IntentReasoner("reference"),
        first.state,
        {"scope": "account", "account_id": "3"},
        "¿Cuánto abono a esta cuenta?",
        [],
    )
    assert followup.clarification is None
    assert followup.state.active_account.account_id == "1"
    assert not any(args.get("account_id") == 3 for _, args in tools.calls)
    january = await resolve_context(
        tools, IntentReasoner("continue"), followup.state, {}, "¿Y en enero?", []
    )
    assert january.state.active_account.account_id == "1"


async def test_collective_requires_clarification_then_resumes_original_question() -> None:
    tools = ContextTools()
    collective = await resolve_context(
        tools,
        IntentReasoner("multiple", account_queries=["Ahorro"]),
        ConversationContext(),
        {},
        "Saldos de ambos ahorros",
        [],
    )
    assert collective.state.scope == "multiple"
    pending = await resolve_context(
        tools,
        IntentReasoner("reference"),
        collective.state,
        {"account_id": "3"},
        "¿Cuánto abono mensualmente a esta cuenta?",
        [],
    )
    assert "Ahorro Javi" in pending.clarification
    assert "Ahorro Div" in pending.clarification
    assert pending.state.pending_question == "¿Cuánto abono mensualmente a esta cuenta?"
    again = await resolve_context(tools, IntentReasoner("continue"), pending.state, {}, "No sé", [])
    assert again.state.pending_question == pending.state.pending_question
    choice = await resolve_context(
        tools,
        IntentReasoner("select", account_ids=["1"], resume_pending=True),
        again.state,
        {},
        "La primera",
        [],
    )
    assert choice.state.active_account.account_id == "1"
    assert choice.state.pending_question is None
    assert pending.state.pending_question in choice.question
    assert "La primera" in choice.question


async def test_explicit_new_account_and_global_override_old_context() -> None:
    tools = ContextTools()
    state = ConversationContext(
        scope="account", active_account=AccountSelection(account_id="1", name="Javi")
    )
    selected = await resolve_context(
        tools, IntentReasoner("select", account_queries=["Div"]), state, {}, "Ahora Div", []
    )
    assert selected.state.active_account.account_id == "2"
    global_result = await resolve_context(
        tools, IntentReasoner("global"), selected.state, {}, "Todas mis cuentas", []
    )
    assert global_result.state.active_account is None
    assert global_result.state.scope == "global"
    ambiguous = await resolve_context(
        tools, IntentReasoner("reference"), global_result.state, {"account_id": "1"}, "¿Y esta?", []
    )
    assert ambiguous.clarification


@pytest.mark.parametrize("query", ["Ahorro", "No existe"])
async def test_ambiguous_or_missing_names_never_choose_first(query: str) -> None:
    result = await resolve_context(
        ContextTools(),
        IntentReasoner("select", account_queries=[query]),
        ConversationContext(),
        {},
        "Saldo",
        [],
    )
    assert result.clarification
    assert result.state.active_account is None


async def test_partial_search_and_unknown_model_ids_cannot_select_account() -> None:
    tools = ContextTools()
    tools.incomplete = True
    result = await resolve_context(
        tools,
        IntentReasoner("select", account_queries=["Javi"]),
        ConversationContext(),
        {},
        "Saldo",
        [],
    )
    assert result.clarification
    unknown = await resolve_context(
        tools, IntentReasoner("select", account_ids=["999"]), ConversationContext(), {}, "Saldo", []
    )
    assert unknown.clarification
    assert not any(args.get("account_id") == 999 for _, args in tools.calls)


async def test_screen_only_initializes_unset_context_and_no_screen_whatsapp_is_ambiguous() -> None:
    tools = ContextTools()
    result = await resolve_context(
        tools,
        IntentReasoner("reference"),
        ConversationContext(),
        {"account_id": "1"},
        "Esta cuenta",
        [],
    )
    assert result.state.active_account.account_id == "1"
    whatsapp = await resolve_context(
        tools,
        IntentReasoner("reference"),
        ConversationContext(),
        {"scope": "global"},
        "Esta cuenta",
        [],
    )
    assert whatsapp.clarification


async def test_revoked_selection_does_not_fall_back_to_screen_or_remaining_account() -> None:
    tools = ContextTools()
    state = ConversationContext(
        scope="multiple",
        candidates=[
            AccountSelection(account_id="1", name="Javi"),
            AccountSelection(account_id="2", name="Div"),
        ],
        pending_question="Pregunta privada",
    )
    del tools.accounts["2"]
    visible = await visible_context(tools, state)
    assert visible.candidates == []
    assert visible.pending_question is None
    result = await resolve_context(
        tools,
        IntentReasoner("continue", resume_pending=True),
        state,
        {"account_id": "3"},
        "¿Y enero?",
        [],
    )
    assert result.clarification
    assert "Pregunta privada" not in result.state.model_dump_json()


async def test_dependency_failure_is_not_revocation() -> None:
    class OfflineTools(ContextTools):
        async def call(self, name: str, args: dict[str, Any]) -> dict[str, Any]:
            raise ToolUnavailable()

    with pytest.raises(ToolUnavailable):
        await visible_context(
            OfflineTools(),
            ConversationContext(
                scope="account", active_account=AccountSelection(account_id="1", name="Javi")
            ),
        )


async def test_resolved_scope_is_enforced_for_financial_queries() -> None:
    tools = ContextTools()
    scoped = ScopedTools(
        tools,
        ConversationContext(
            scope="account", active_account=AccountSelection(account_id="1", name="Javi")
        ),
    )
    await scoped.call("summarize_expenses", {"start_date": "2026-01-01", "end_date": "2026-01-31"})
    assert tools.calls[-1][1]["account_ids"] == [1]
    for name, args in [
        ("get_account_balance", {"account_id": 2}),
        ("list_account_balances", {}),
        ("list_expenses", {"account_ids": [2]}),
    ]:
        with pytest.raises(ToolValidation):
            await scoped.call(name, args)


def test_context_survives_store_recreation_and_cancel_does_not_change_it(store: Store) -> None:
    actor = Actor(actor_user_id=1)
    tid = store.create_thread(CreateThread(actor_user_id=1, client_request_id=uuid4()))["id"]
    rid = store.create_run(
        tid, CreateRun(actor_user_id=1, client_request_id=uuid4(), message="Ahorro")
    )["run_id"]
    assert store.claim(30)
    context = ConversationContext(
        scope="account", active_account=AccountSelection(account_id="1", name="Ahorro")
    )
    assert store.finish(rid, "Saldo", [{"account_id": "1"}], conversation_context=context)
    fresh = Store(store.engine)
    assert fresh.conversation_context(tid, actor) == context
    with pytest.raises(StoreError):
        fresh.conversation_context(tid, Actor(actor_user_id=2))
    with pytest.raises(StoreError):
        fresh.conversation_context(tid, Actor(actor_user_id=1, channel="whatsapp"))
    rid = fresh.create_run(
        tid, CreateRun(actor_user_id=1, client_request_id=uuid4(), message="Otra")
    )["run_id"]
    assert fresh.claim(30)
    fresh.cancel(tid, rid, actor)
    assert not fresh.finish(rid, "Tarde", conversation_context=ConversationContext(scope="global"))
    assert fresh.conversation_context(tid, actor) == context
    other = fresh.create_thread(CreateThread(actor_user_id=1, client_request_id=uuid4()))["id"]
    assert fresh.conversation_context(other, actor).scope == "unset"


@pytest.mark.parametrize("has_candidates", [False, True])
async def test_independent_question_can_abandon_pending_without_changing_selection(
    has_candidates: bool,
) -> None:
    state = ConversationContext(
        scope="multiple",
        candidates=[AccountSelection(account_id="1", name="Javi")] if has_candidates else [],
        pending_question="¿Cuánto abono?",
    )
    result = await resolve_context(
        ContextTools(),
        IntentReasoner("continue", abandon_pending=True),
        state,
        {},
        "¿Qué puedes consultar?",
        [],
    )
    assert result.state.pending_question is None
    assert result.clarification is None


async def test_ignored_screen_does_not_hide_completed_messages(store: Store) -> None:
    from finance_agent.mcp_client import visible_messages
    from finance_agent.schemas import Context

    tools = ContextTools()
    actor = Actor(actor_user_id=1)
    tid = store.create_thread(CreateThread(actor_user_id=1, client_request_id=uuid4()))["id"]
    rid = store.create_run(
        tid,
        CreateRun(
            actor_user_id=1,
            client_request_id=uuid4(),
            message="Saldo",
            context=Context(scope="account", account_id="3"),
        ),
    )["run_id"]
    assert store.claim(30)
    context = ConversationContext(
        scope="account", active_account=AccountSelection(account_id="1", name="Javi")
    )
    store.finish(
        rid,
        "Saldo",
        [{"account_id": "1"}],
        conversation_title="Consulta de saldo",
        conversation_context=context,
    )
    del tools.accounts["3"]
    messages = store.history(tid, actor)["items"]
    assert len(await visible_messages(tools, messages)) == 2
    assert store.list_threads(actor, None)["items"][0]["title_references"] == [{"account_id": "1"}]


async def test_worker_multiturn_persists_selection_and_resumes_after_restart(
    store: Store, monkeypatch: pytest.MonkeyPatch
) -> None:
    from collections.abc import AsyncIterator
    from contextlib import asynccontextmanager

    from langchain_core.messages import AIMessage

    from finance_agent import worker
    from finance_agent.graph import Answer
    from finance_agent.schemas import Context
    from finance_agent.settings import Settings

    tools = ContextTools()
    actor = Actor(actor_user_id=1)
    tid = store.create_thread(CreateThread(actor_user_id=1, client_request_id=uuid4()))["id"]
    seen: list[str] = []

    class ScriptedReasoner(IntentReasoner):
        async def next(
            self, messages: list[BaseMessage], catalog: list[dict[str, Any]]
        ) -> AIMessage:
            seen.append(str(messages[-1].content))
            return AIMessage(content="Ready")

        async def answer(self, messages: list[BaseMessage]) -> Answer:
            return Answer(content="Respuesta simulada")

    @asynccontextmanager
    async def connector(*_: Any) -> AsyncIterator[ContextTools]:
        yield tools

    monkeypatch.setattr(worker, "connect_tools", connector)
    turns = [
        ("Saldos de los ahorros", "multiple", {"account_queries": ["Ahorro"]}, "multiple"),
        ("¿Cuánto abono a esta cuenta?", "reference", {}, "multiple"),
        ("Javi", "select", {"account_ids": ["1"], "resume_pending": True}, "account"),
        ("¿Y en enero?", "continue", {}, "account"),
        ("Ahora Div", "select", {"account_queries": ["Div"]}, "account"),
    ]
    for message, mode, kwargs, scope in turns:
        reasoner = ScriptedReasoner(mode, **kwargs)
        monkeypatch.setattr(worker, "ChatReasoner", lambda _, reasoner=reasoner: reasoner)
        fresh = Store(store.engine)
        rid = fresh.create_run(
            tid,
            CreateRun(
                actor_user_id=1,
                client_request_id=uuid4(),
                message=message,
                context=Context(scope="account", account_id="3"),
            ),
        )["run_id"]
        claimed = fresh.claim(30)
        assert claimed
        await worker.execute(
            fresh, Settings(_env_file=None, agent_history_limit=2), claimed[0], actor, None
        )
        result = fresh.get_run(tid, rid, actor)
        assert result["status"] == "completed"
        assert result["message"]["conversation_context"]["scope"] == scope
        assert fresh.conversation_context(tid, actor).scope == scope
        if message == "Javi":
            assert "¿Cuánto abono a esta cuenta?" in seen[-1]
        if message == "¿Y en enero?":
            assert fresh.conversation_context(tid, actor).active_account.account_id == "1"
    assert fresh.conversation_context(tid, actor).active_account.account_id == "2"
