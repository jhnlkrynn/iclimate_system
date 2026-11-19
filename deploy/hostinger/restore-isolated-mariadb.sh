#!/usr/bin/env bash
set -Eeuo pipefail

# Explicitly destructive only to the newly named disposable database. It never
# drops databases and refuses names outside the approved temporary prefix.
: "${DUMP_FILE:?DUMP_FILE is required}"
: "${MYSQL_HOST:?MYSQL_HOST is required}"
: "${MYSQL_PORT:?MYSQL_PORT is required}"
: "${MYSQL_ADMIN_USER:?MYSQL_ADMIN_USER is required}"
: "${MYSQL_ADMIN_PASSWORD:?MYSQL_ADMIN_PASSWORD is required}"
: "${RESTORE_DATABASE:?RESTORE_DATABASE is required}"

[[ "$RESTORE_DATABASE" =~ ^iclimate_restore_[0-9]{8}_[0-9]{6}$ ]] || { echo 'RESTORE_DATABASE must be a unique iclimate_restore_ timestamp name.' >&2; exit 2; }
test -f "$DUMP_FILE"
(cd "$(dirname "$DUMP_FILE")" && sha256sum -c "$(basename "$DUMP_FILE").sha256")

MYSQL_PWD="$MYSQL_ADMIN_PASSWORD" mysql --host="$MYSQL_HOST" --port="$MYSQL_PORT" --user="$MYSQL_ADMIN_USER" \
  --batch --skip-column-names \
  -e "SELECT CASE WHEN COUNT(*) = 0 THEN 'unused' ELSE 'occupied' END FROM information_schema.schemata WHERE schema_name = '$RESTORE_DATABASE';" \
  | grep -Fx unused >/dev/null

MYSQL_PWD="$MYSQL_ADMIN_PASSWORD" mysql --host="$MYSQL_HOST" --port="$MYSQL_PORT" --user="$MYSQL_ADMIN_USER" \
  -e "CREATE DATABASE \`$RESTORE_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

MYSQL_PWD="$MYSQL_ADMIN_PASSWORD" mysql --host="$MYSQL_HOST" --port="$MYSQL_PORT" --user="$MYSQL_ADMIN_USER" \
  "$RESTORE_DATABASE" < "$DUMP_FILE"

MYSQL_PWD="$MYSQL_ADMIN_PASSWORD" mysqlcheck --host="$MYSQL_HOST" --port="$MYSQL_PORT" --user="$MYSQL_ADMIN_USER" \
  --databases "$RESTORE_DATABASE" --check
printf 'Restore completed in disposable database %s. Preserve it for review; no cleanup was performed.\n' "$RESTORE_DATABASE"

