#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if ! command -v uv >/dev/null 2>&1; then
    echo 'Install uv to run LangGraph Studio development server.' >&2
    exit 1
fi
printf '%s\n' 'LangGraph: http://127.0.0.1:2024' 'Studio: https://smith.langchain.com/studio/?baseUrl=http://127.0.0.1:2024'
exec uv run --frozen langgraph dev --host 127.0.0.1 --port 2024 --no-browser
