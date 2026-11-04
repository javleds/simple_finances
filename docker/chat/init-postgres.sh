#!/usr/bin/env bash
set -euo pipefail
psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" --set=runtime_password="$AGENT_DATABASE_PASSWORD" <<'SQL'
CREATE ROLE agent_runtime LOGIN PASSWORD :'runtime_password';
GRANT CONNECT ON DATABASE agent TO agent_runtime;
GRANT USAGE ON SCHEMA public TO agent_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE agent_owner IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO agent_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE agent_owner IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO agent_runtime;
SQL
