#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if ! command -v uv >/dev/null 2>&1; then
    echo 'Install uv to run LangGraph Studio development server.' >&2
    exit 1
fi
exec node ../../scripts/agents-dev.mjs
