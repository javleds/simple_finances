FROM python:3.12-slim-bookworm

ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 UV_PROJECT_ENVIRONMENT=/opt/venv PATH="/opt/venv/bin:$PATH"
RUN pip install --no-cache-dir uv==0.9.9 && useradd --create-home --uid 10001 agent
WORKDIR /app
COPY apps/agents/pyproject.toml apps/agents/uv.lock ./
RUN uv sync --frozen --no-dev --no-install-project
COPY apps/agents/ ./
RUN uv sync --frozen --no-dev && chown -R agent:agent /app /opt/venv
USER agent
CMD ["uvicorn", "finance_agent.api:app", "--host", "0.0.0.0", "--port", "8000"]
