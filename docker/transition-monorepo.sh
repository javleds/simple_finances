#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

[[ $# == 4 ]] || { printf 'Usage: transition-monorepo.sh CHECKOUT TARGET_COMMIT BACKUP_DIRECTORY COMPOSE_PROJECT_NAME\n' >&2; exit 1; }
repository="$(cd -- "$1" && pwd)"
target="$2"
backup="$3"
project="$4"
[[ "$backup" == /* && "$backup" != "$repository" && "$backup" != "$repository/"* && ! -e "$backup" ]] || { printf 'Backup must be a new absolute directory outside the checkout.\n' >&2; exit 1; }
[[ "$project" =~ ^[a-z0-9][a-z0-9_-]*$ ]] || { printf 'Invalid Compose project name.\n' >&2; exit 1; }
cd "$repository"
[[ -f .env && -f artisan && ! -e apps/finance/artisan ]] || { printf 'Expected the original root Laravel layout.\n' >&2; exit 1; }
[[ -z "$(git status --porcelain --untracked-files=all)" ]] || { printf 'Checkout must be clean.\n' >&2; exit 1; }
target="$(git rev-parse --verify "$target^{commit}")"
git cat-file -e "$target:apps/finance/artisan"
git cat-file -e "$target:packages/finance-domain/composer.json"
existing_project="$(docker inspect --format '{{ index .Config.Labels "com.docker.compose.project" }}' simple_finances_db_prod)"
existing_volume="$(docker inspect --format '{{ range .Mounts }}{{ if eq .Destination "/var/lib/mysql" }}{{ .Name }}{{ end }}{{ end }}' simple_finances_db_prod)"
[[ "$existing_project" == "$project" && "$existing_volume" == "${project}_simplefinancesdb" ]] || { printf 'Existing MySQL project or volume does not match.\n' >&2; exit 1; }
exec 9>/tmp/fin-si.deploy.lock
flock -n 9 || { printf 'Another deployment is running.\n' >&2; exit 1; }
compose() { docker compose --env-file "$repository/.env" -p "$project" -f "$repository/docker-compose.prod.yml" "$@"; }
trap 'printf "Transition failed; keep traffic and consumers stopped. Recovery files: %s\n" "$backup" >&2' ERR
compose config --quiet
compose exec -T php php artisan down --retry=60
services="$(compose config --services)"
for service in attachments-worker attachments-maintenance; do
  if grep -Fxq "$service" <<< "$services"; then
    compose stop --timeout 90 "$service"
  fi
done
compose stop --timeout 90 nginx php
umask 077
mkdir -p "$backup"
git rev-parse HEAD > "$backup/previous-commit"
printf '%s\n' "$project" > "$backup/compose-project"
printf '%s\n' "$existing_volume" > "$backup/mysql-volume"
cp -p .env "$backup/.env"
cp -p docker-compose.prod.yml "$backup/docker-compose.prod.yml"
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
git checkout --detach "$target"
cp -p "$backup/.env" apps/finance/.env
sed '/^[[:space:]]*COMPOSE_PROJECT_NAME=/d' apps/finance/.env > apps/finance/.env.transition
printf '\nCOMPOSE_PROJECT_NAME=%s\n' "$project" >> apps/finance/.env.transition
mv apps/finance/.env.transition apps/finance/.env
cp -a "$backup/storage/." apps/finance/storage/
if [[ -d "$backup/build" ]]; then
  mkdir -p apps/finance/public/build
  cp -a "$backup/build/." apps/finance/public/build/
fi
rm -f apps/finance/public/hot
flock -u 9
exec bash "$repository/deploy.sh" --no-pull
