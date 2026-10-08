#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

PROJECT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE=docker-compose.prod.yml
LOG_DIR="${DEPLOY_LOG_DIR:-$HOME/deploy-logs}"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/fin-si-$(date +'%Y%m%d-%H%M%S').log"
exec 9>/tmp/fin-si.deploy.lock
flock -n 9 || { printf 'Another fin-si deployment is running.\n' >&2; exit 1; }
cd "$PROJECT_DIR"

log() { printf '[%s] %s\n' "$(date +'%F %T')" "$*" | tee -a "$LOG_FILE" >&2; }
run() { log "RUN: $*"; "$@" 2>&1 | tee -a "$LOG_FILE"; }
compose() { docker compose -f "$COMPOSE_FILE" "$@"; }
maintenance_started=false
on_error() {
  local status=$?
  log "Deployment failed. Log: $LOG_FILE"
  if [[ "$maintenance_started" == true ]]; then
    log 'Maintenance remains enabled. Fix the failure and rerun deploy.sh; do not enable traffic with mismatched code and assets.'
  fi
  exit "$status"
}
trap on_error ERR

[[ -f .env ]] || { log 'Missing Laravel .env'; exit 1; }
for key in TRAEFIK_APP_NAME APP_DOMAIN API_DOMAIN APP_URL SPA_URL FRONTEND_URL; do
  if ! grep -Eq "^[[:space:]]*${key}=[^[:space:]]+" .env; then
    log "Missing required configuration: $key"
    exit 1
  fi
done
[[ -z "$(git status --porcelain --untracked-files=all)" ]] || { log 'Commit or remove local changes before deployment.'; exit 1; }
run compose config --quiet
previous_commit="$(git rev-parse HEAD)"
log "Previous revision: $previous_commit"

if [[ -n "$(compose ps -q php)" ]]; then
  run compose exec -T php php artisan down --retry=60
  maintenance_started=true
fi
run git pull --ff-only
run compose config --quiet
run compose build --pull php
run compose run --rm --no-deps php composer check-platform-reqs --no-dev --lock
run compose run --rm --no-deps php composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
run compose --profile build run --rm --no-deps assets

if [[ "$maintenance_started" == false ]]; then
  run compose run --rm --no-deps php php artisan down --retry=60
  maintenance_started=true
fi
run compose --profile build run --rm --no-deps assets node scripts/publish-assets.mjs
run compose up -d --wait php mysql nginx
run compose exec -T php php artisan app:post-deploy --migrate
run compose exec -T php php artisan up
maintenance_started=false
log "Deployment complete: $(git rev-parse --short HEAD)"
