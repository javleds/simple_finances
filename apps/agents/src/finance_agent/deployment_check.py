import sys

import httpx
from sqlalchemy import select

from finance_agent.models import Event, Message, Run, Thread
from finance_agent.settings import get_settings
from finance_agent.store import make_engine


def main() -> int:
    engine = None
    try:
        settings = get_settings()
        engine = make_engine(settings.database_url)
        with engine.connect() as connection:
            for model in (Thread, Run, Message, Event):
                connection.execute(select(model).limit(0))
        with httpx.Client(timeout=10) as client:
            response = client.get(settings.mcp_url.removesuffix("/mcp") + "/up")
            response.raise_for_status()
    except Exception:
        print("Agent schema or MCP connectivity check failed.", file=sys.stderr)
        return 1
    finally:
        if engine is not None:
            engine.dispose()
    print("Agent schema and MCP connectivity checks passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
