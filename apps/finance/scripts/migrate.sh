#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ "$(uname -s)" == "Darwin" ]] && command -v brew >/dev/null 2>&1; then
    php_prefix="$(HOMEBREW_NO_AUTO_UPDATE=1 brew --prefix php)"
    export PATH="$php_prefix/bin:$php_prefix/sbin:$PATH"
fi

exec php artisan migrate
