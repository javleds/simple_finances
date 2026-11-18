#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

PROJECT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$PROJECT_DIR/apps/finance"
ENV_FILE="$APP_DIR/.env"
COMPOSE_FILE=docker-compose.prod.yml
[[ "${1:-}" == "" || "${1:-}" == "--no-pull" ]] || { printf 'Usage: bash deploy.sh [--no-pull]\n' >&2; exit 1; }
LOG_DIR="${DEPLOY_LOG_DIR:-$HOME/deploy-logs}"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/fin-si-$(date +'%Y%m%d-%H%M%S').log"
exec 9>/tmp/fin-si.deploy.lock
flock -n 9 || { printf 'Another fin-si deployment is running.\n' >&2; exit 1; }
cd "$PROJECT_DIR"

log() { printf '[%s] %s\n' "$(date +'%F %T')" "$*" | tee -a "$LOG_FILE" >&2; }
run() { log "RUN: $*"; "$@" 2>&1 | tee -a "$LOG_FILE"; }
deployment_mode=finance
compose_command() {
  env -u AGENT_DATABASE_OWNER_PASSWORD -u AGENT_DATABASE_PASSWORD -u DB_DATABASE -u DB_PASSWORD \
    -u APP_DOMAIN -u API_DOMAIN -u TRAEFIK_APP_NAME -u SPA_LEGACY_DOMAIN -u DB_TZ \
    docker compose --project-name "$COMPOSE_PROJECT_NAME" "$@"
}
base_compose() { compose_command --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"; }
compose() {
  if [[ "$deployment_mode" == chat ]]; then
    compose_command --env-file "$PROJECT_DIR/.env" --env-file "$ENV_FILE" \
      -f "$COMPOSE_FILE" -f docker-compose.chat.yml --profile chat "$@"
    return
  fi
  base_compose "$@"
}
validate_configuration() {
  deployment_mode="$(base_compose --profile build run --rm --no-deps assets node ../../scripts/validate-production.mjs)"
  [[ "$deployment_mode" == finance || "$deployment_mode" == chat ]] || { log 'Invalid production preflight result.'; return 1; }
  run compose config --quiet
}
stop_chat_consumers() {
  local service ids
  for service in agent-api agent-worker agent-maintenance agent-mcp mcp-php; do
    ids="$(docker ps -aq --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME" --filter "label=com.docker.compose.service=$service")" || return
    if [[ -n "$ids" ]]; then
      while IFS= read -r id; do
        run docker stop --time 130 "$id" || return
      done <<< "$ids"
    fi
  done
}
maintenance_started=false
deployment_started=false
stop_attachment_consumers() {
  local services
  services="$(compose config --services)" || return
  for service in attachments-worker attachments-maintenance; do
    if grep -Fxq "$service" <<< "$services"; then
      run compose stop --timeout 90 "$service" || return
    fi
  done
}
on_error() {
  local status=$?
  trap - ERR
  log "Deployment failed. Log: $LOG_FILE"
  if [[ "$deployment_started" == true ]]; then
    stop_attachment_consumers || log "Could not stop attachment consumers; stop them manually before recovery."
    stop_chat_consumers || log "Could not stop chat consumers; stop them manually before recovery."
  fi
  if [[ "$maintenance_started" == true ]]; then
    log 'Maintenance remains enabled. Fix the failure and rerun deploy.sh; do not enable traffic with mismatched code and assets.'
  fi
  exit "$status"
}
trap on_error ERR

[[ -f "$ENV_FILE" ]] || { log 'Missing apps/finance/.env; use the separate monorepo transition procedure for an old checkout.'; exit 1; }
for key in TRAEFIK_APP_NAME APP_DOMAIN API_DOMAIN APP_URL SPA_URL FRONTEND_URL COMPOSE_PROJECT_NAME; do
  if ! grep -Eq "^[[:space:]]*${key}=[^[:space:]]+" "$ENV_FILE"; then
    log "Missing required configuration: $key"
    exit 1
  fi
done
[[ -z "$(git status --porcelain --untracked-files=all)" ]] || { log 'Commit or remove local changes before deployment.'; exit 1; }
configured_project="$(sed -n 's/^COMPOSE_PROJECT_NAME=\([a-z0-9][a-z0-9_-]*\)$/\1/p' "$ENV_FILE")"
[[ -n "$configured_project" && "$configured_project" != *$'\n'* ]] || { log 'COMPOSE_PROJECT_NAME must be one unquoted explicit project name.'; exit 1; }
[[ -z "${COMPOSE_PROJECT_NAME:-}" || "$COMPOSE_PROJECT_NAME" == "$configured_project" ]] || { log 'Shell and application Compose project names differ.'; exit 1; }
export COMPOSE_PROJECT_NAME="$configured_project"
if docker inspect simple_finances_db_prod >/dev/null 2>&1; then
  existing_project="$(docker inspect --format '{{ index .Config.Labels "com.docker.compose.project" }}' simple_finances_db_prod)"
  existing_volume="$(docker inspect --format '{{ range .Mounts }}{{ if eq .Destination "/var/lib/mysql" }}{{ .Name }}{{ end }}{{ end }}' simple_finances_db_prod)"
  [[ "$existing_project" == "$COMPOSE_PROJECT_NAME" && "$existing_volume" == "${COMPOSE_PROJECT_NAME}_simplefinancesdb" ]] || { log 'Existing MySQL project or volume differs; reconcile the recorded deployment identity before continuing.'; exit 1; }
fi
validate_configuration
previous_commit="$(git rev-parse HEAD)"
log "Previous revision: $previous_commit"

deployment_started=true
if [[ -n "$(compose ps -q php)" ]]; then
  run compose exec -T php php artisan down --retry=60
  maintenance_started=true
fi
stop_attachment_consumers
stop_chat_consumers
run compose stop --timeout 90 nginx php
script_revision="$(git hash-object deploy.sh)"
if [[ "${1:-}" != "--no-pull" ]]; then
  run git pull --ff-only
fi
if [[ "$(git hash-object deploy.sh)" != "$script_revision" ]]; then
  log 'deploy.sh changed during pull. Services remain stopped; rerun bash deploy.sh --no-pull to use the updated entrypoint.'
  false
fi
updated_project="$(sed -n 's/^COMPOSE_PROJECT_NAME=\([a-z0-9][a-z0-9_-]*\)$/\1/p' "$ENV_FILE")"
[[ "$updated_project" == "$configured_project" ]] || { log 'Compose project changed during deployment.'; false; }
validate_configuration
run compose build --pull php
if [[ "$deployment_mode" == chat ]]; then
  run compose build --pull agent-api mcp-php
fi
run compose run --rm --no-deps php composer check-platform-reqs --no-dev --lock
run compose run --rm --no-deps php composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
run compose run --rm --no-deps php composer reinstall fin-si/finance-domain --no-interaction --optimize-autoloader
run compose --profile build run --rm --no-deps assets

if [[ "$maintenance_started" == false ]]; then
  run compose run --rm --no-deps php php artisan down --retry=60
  maintenance_started=true
fi
run compose --profile build run --rm --no-deps assets node scripts/publish-assets.mjs
run compose up -d --wait php mysql nginx
run compose exec -T php php artisan app:post-deploy --migrate
run compose exec -T php php artisan storage:link --force
if [[ "$deployment_mode" == chat ]]; then
  run compose up -d --wait agent-postgres
  run compose run --rm --no-deps agent-migrate
  run compose up -d --force-recreate --wait mcp-php agent-mcp agent-api
  legal_hashes="$(compose exec -T php php scripts/check-agent.php --legal-hashes)"
  run compose exec -T mcp-php php scripts/check-production.php "$legal_hashes"
  run compose exec -T agent-api python -m finance_agent.deployment_check
  run compose exec -T php php scripts/check-agent.php
  run compose up -d --force-recreate --wait agent-worker agent-maintenance
fi
run compose up -d --force-recreate attachments-worker attachments-maintenance
run compose exec -T php php artisan attachments:diagnose --timeout=120
run compose exec -T php php artisan up
maintenance_started=false
log "Deployment complete: $(git rev-parse --short HEAD)"
