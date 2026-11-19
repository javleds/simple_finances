#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

usage() {
  cat <<'HELP'
Usage: transition-monorepo.sh --target COMMIT [--check | --apply]
       [--repo PATH] [--backup PATH] [--project NAME]

Defaults: --check, --repo ~/apps/simple_finances; project detected from existing MySQL.
Backup defaults to a new timestamped sibling of the checkout.
Copy this script outside the checkout before running. Fetch the target revision first.
Stop external schedulers/workers before --apply. Traefik is not modified.
The first transition deploys Finance only; chat remains disabled until separately configured.
Legacy invocation: transition-monorepo.sh CHECKOUT COMMIT BACKUP_DIRECTORY PROJECT
HELP
}

mode=check
repository="$HOME/apps/simple_finances"
target=''
backup=''
project=''
if [[ $# == 4 && "$1" != --* ]]; then
  repository="$1"; target="$2"; backup="$3"; project="$4"; mode=apply
  shift 4
fi
while [[ $# -gt 0 ]]; do
  case "$1" in
    --check) mode=check; shift ;;
    --apply) mode=apply; shift ;;
    --repo|--target|--backup|--project)
      [[ $# -ge 2 && -n "$2" ]] || { usage >&2; exit 1; }
      case "$1" in
        --repo) repository="$2" ;;
        --target) target="$2" ;;
        --backup) backup="$2" ;;
        --project) project="$2" ;;
      esac
      shift 2 ;;
    --help|-h) usage; exit 0 ;;
    *) usage >&2; exit 1 ;;
  esac
done
[[ -n "$target" ]] || { usage >&2; exit 1; }
repository="$(cd -- "$repository" && pwd -P)"
script="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)/$(basename -- "${BASH_SOURCE[0]}")"
[[ "$script" != "$repository/"* ]] || { printf 'Copy this script outside the checkout before executing it.\n' >&2; exit 1; }
backup="${backup:-$(dirname -- "$repository")/$(basename -- "$repository")-backup-$(date +%Y%m%d-%H%M%S)}"
[[ "$backup" == /* && ! -e "$backup" && ! -L "$backup" ]] || { printf 'Backup must be a new absolute directory.\n' >&2; exit 1; }
backup="$(cd -- "$(dirname -- "$backup")" && pwd -P)/$(basename -- "$backup")"
[[ "$backup" != "$repository" && "$backup" != "$repository/"* ]] || { printf 'Backup must be outside the checkout, including through symlinks.\n' >&2; exit 1; }
cd "$repository"
[[ "$(git rev-parse --show-toplevel)" == "$repository" ]] || { printf 'Expected the Git checkout root.\n' >&2; exit 1; }
[[ -f .env && ! -L .env && -f artisan && -d storage && ! -L storage && ! -L apps && ! -e apps/finance/artisan ]] || { printf 'Expected the original root Laravel layout; an existing monorepo must use deploy.sh.\n' >&2; exit 1; }
for file in apps/finance/.env apps/mcp/.env apps/agents/.env .env.chat; do
  [[ ! -e "$file" && ! -L "$file" ]] || { printf 'Preserve and reconcile existing configuration before transition: %s\n' "$file" >&2; exit 1; }
done
[[ -z "$(git status --porcelain --untracked-files=all)" ]] || { printf 'Checkout must be clean; preserve untracked files outside it first.\n' >&2; exit 1; }
target="$(git rev-parse --verify --end-of-options "$target^{commit}")"
for file in apps/finance/artisan packages/finance-domain/composer.json deploy.sh scripts/validate-production.mjs apps/mcp/.env.example apps/agents/.env.example; do
  git cat-file -e "$target:$file"
done
missing=false
for key in APP_KEY DB_DATABASE DB_PASSWORD TRAEFIK_APP_NAME APP_DOMAIN API_DOMAIN APP_URL SPA_URL FRONTEND_URL; do
  if ! grep -Eq "^[[:space:]]*${key}=[^[:space:]]+" .env; then
    printf 'Set %s in the original .env before transition; existing domains are not guessed.\n' "$key" >&2
    missing=true
  fi
done
[[ "$missing" == false ]] || exit 1
grep -Eq "^[[:space:]]*APP_ENV=(production|\"production\"|'production')[[:space:]]*(#.*)?$" .env || { printf 'APP_ENV must be production before transition.\n' >&2; exit 1; }
if grep -Eq "^[[:space:]]*APP_DEBUG=" .env; then
  grep -Eq "^[[:space:]]*APP_DEBUG=(false|0|\"false\"|'false')[[:space:]]*(#.*)?$" .env || { printf 'APP_DEBUG must be false before transition.\n' >&2; exit 1; }
fi
literal_setting() {
  local value
  value="$(sed -n "s/^$1=//p" .env)"
  case "$value" in
    \"*\") value="${value#\"}"; value="${value%\"}" ;;
    \'*\') value="${value#\'}"; value="${value%\'}" ;;
  esac
  printf '%s' "$value"
}
db_port="$(literal_setting DB_PORT)"
[[ "$(literal_setting DB_CONNECTION)" == mysql && "$(literal_setting DB_HOST)" == mysql ]] || { printf 'This transition requires Finance to use the existing mysql Docker service.\n' >&2; exit 1; }
[[ -z "$db_port" || "$db_port" == 3306 ]] || { printf 'Expected internal MySQL port 3306.\n' >&2; exit 1; }
for command in docker flock gzip; do command -v "$command" >/dev/null; done
existing_project="$(docker inspect --format '{{ index .Config.Labels "com.docker.compose.project" }}' simple_finances_db_prod)"
existing_volume="$(docker inspect --format '{{ range .Mounts }}{{ if eq .Destination "/var/lib/mysql" }}{{ .Name }}{{ end }}{{ end }}' simple_finances_db_prod)"
container_database="$(docker inspect --format '{{range .Config.Env}}{{println .}}{{end}}' simple_finances_db_prod | sed -n 's/^MYSQL_DATABASE=//p')"
[[ -n "$container_database" && "$container_database" == "$(literal_setting DB_DATABASE)" ]] || { printf 'Finance database differs from the MySQL container database; reconcile the backup target first.\n' >&2; exit 1; }
project="${project:-$existing_project}"
[[ "$project" =~ ^[a-z0-9][a-z0-9_-]*$ ]] || { printf 'Invalid detected or requested Compose project name.\n' >&2; exit 1; }
[[ "$existing_project" == "$project" && "$existing_volume" == "${project}_simplefinancesdb" ]] || { printf 'Existing MySQL project or volume does not match; do not create a replacement volume.\n' >&2; exit 1; }
docker network inspect proxy >/dev/null
compose() { docker compose --env-file "$repository/.env" -p "$project" -f "$repository/docker-compose.prod.yml" "$@"; }
compose config --quiet
printf 'Checkout: %s\nTarget: %s\nProject: %s\nMySQL volume: %s\nBackup: %s\n' "$repository" "$target" "$project" "$existing_volume" "$backup"
printf 'Traefik is unchanged. Finance env/storage move to apps/finance; chat starts disabled.\n'
if [[ "$mode" == check ]]; then
  printf 'Preflight passed. No services or files changed. Run with --apply after stopping external writers.\n'
  exit 0
fi
exec 9>/tmp/fin-si.deploy.lock
flock -n 9 || { printf 'Another deployment is running.\n' >&2; exit 1; }
[[ -z "$(git status --porcelain --untracked-files=all)" ]] || { printf 'Checkout changed during preflight.\n' >&2; exit 1; }
stage=maintenance
on_error() {
  local status=$?
  trap - ERR
  printf 'Transition failed at %s. Keep traffic and consumers stopped. Recovery files: %s\n' "$stage" "$backup" >&2
  exit "$status"
}
trap on_error ERR
compose exec -T php php artisan down --retry=60
services="$(compose config --services)"
while IFS= read -r service; do
  if [[ "$service" != mysql && "$service" != nginx && "$service" != php && -n "$service" ]]; then
    compose stop --timeout 90 "$service"
  fi
done <<< "$services"
compose stop --timeout 90 nginx php
original_umask="$(umask)"
umask 077
mkdir "$backup"
git rev-parse HEAD > "$backup/previous-commit"
printf '%s\n' "$target" > "$backup/target-commit"
printf '%s\n' "$project" > "$backup/compose-project"
printf '%s\n' "$existing_volume" > "$backup/mysql-volume"
cp -p .env "$backup/.env"
chmod 600 "$backup/.env"
cp -p docker-compose.prod.yml "$backup/docker-compose.prod.yml"
stage=mysql-backup
printf '%s\n' "$stage" > "$backup/stage"
compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump --user=root --single-transaction --quick --routines --events --triggers --no-tablespaces --set-gtid-purged=OFF --databases "$MYSQL_DATABASE"' | gzip > "$backup/mysql.sql.gz.partial"
gzip -t "$backup/mysql.sql.gz.partial"
[[ "$(gzip -dc "$backup/mysql.sql.gz.partial" | wc -c)" -gt 0 ]] || { printf 'MySQL dump was empty.\n' >&2; false; }
mv "$backup/mysql.sql.gz.partial" "$backup/mysql.sql.gz"
stage=preserve-runtime
printf '%s\n' "$stage" > "$backup/stage"
mv storage "$backup/storage"
if [[ -d public/build ]]; then
  mv public/build "$backup/build"
fi
mkdir -p "$backup/legacy-runtime"
for path in vendor node_modules bootstrap/cache public/hot public/storage .phpunit.cache .php-cs-fixer.cache; do
  if [[ -e "$path" || -L "$path" ]]; then
    destination="$backup/legacy-runtime/$path"
    mkdir -p "$(dirname "$destination")"
    mv "$path" "$destination"
  fi
done
umask "$original_umask"
stage=checkout
printf '%s\n' "$stage" > "$backup/stage"
git checkout --detach "$target"
stage=restore-layout
printf '%s\n' "$stage" > "$backup/stage"
mkdir -p apps/finance/storage apps/finance/bootstrap/cache apps/finance/public
(umask 077; sed -E '/^[[:space:]]*(COMPOSE_PROJECT_NAME|CHAT_ENABLED)=/d' "$backup/.env" > apps/finance/.env)
printf '\nCOMPOSE_PROJECT_NAME=%s\nCHAT_ENABLED=false\n' "$project" >> apps/finance/.env
chmod 600 apps/finance/.env
(umask 077; printf 'COMPOSE_PROJECT_NAME=%s\nAGENT_DATABASE_OWNER_PASSWORD=\nAGENT_DATABASE_PASSWORD=\n' "$project" > .env)
while IFS= read -r line; do
  if [[ "$line" =~ ^(DB_DATABASE|DB_PASSWORD|DB_TZ)= ]]; then
    printf '%s\n' "$line" >> .env
  fi
done < "$backup/.env"
chmod 600 .env
for app in mcp agents; do
  (umask 077; cat "apps/$app/.env.example" > "apps/$app/.env")
done
cp -a "$backup/storage/." apps/finance/storage/
if [[ -d "$backup/build" ]]; then
  mkdir -p apps/finance/public/build
  cp -a "$backup/build/." apps/finance/public/build/
fi
rm -f apps/finance/public/hot
stage=deploy
printf '%s\n' "$stage" > "$backup/stage"
flock -u 9
bash "$repository/deploy.sh" --no-pull
printf 'complete\n' > "$backup/stage"
printf 'Transition complete. Backup retained at %s. Configure MCP/agent separately before enabling chat.\n' "$backup"
