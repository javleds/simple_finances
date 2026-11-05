from finance_agent.settings import get_settings
from finance_agent.store import Store, make_engine


def main() -> None:
    settings = get_settings()
    store = Store(make_engine(settings.database_url))
    try:
        store.purge(settings.agent_retention_days)
    finally:
        store.engine.dispose()


if __name__ == "__main__":
    main()
