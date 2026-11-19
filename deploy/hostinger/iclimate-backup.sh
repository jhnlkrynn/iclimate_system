#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

# Run from a protected service context. Required variables are supplied by an
# unreadable environment file; no password is accepted on the command line.
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${BACKUP_DB_HOST:?BACKUP_DB_HOST is required}"
: "${BACKUP_DB_PORT:?BACKUP_DB_PORT is required}"
: "${BACKUP_DB_USERNAME:?BACKUP_DB_USERNAME is required}"
: "${BACKUP_DB_PASSWORD:?BACKUP_DB_PASSWORD is required}"
: "${BACKUP_DIRECTORY:?BACKUP_DIRECTORY is required}"

DUMP_BIN="${MYSQLDUMP_BINARY:-mariadb-dump}"
mkdir -p -- "$BACKUP_DIRECTORY"
chmod 700 -- "$BACKUP_DIRECTORY"
stamp="$(date -u +%Y%m%d-%H%M%S)-$$"
final="$BACKUP_DIRECTORY/iclimate_${stamp}.sql"
partial="$final.partial"
trap 'rm -f -- "$partial" "$partial.sha256"' ERR INT TERM

MYSQL_PWD="$BACKUP_DB_PASSWORD" "$DUMP_BIN" \
  --host="$BACKUP_DB_HOST" --port="$BACKUP_DB_PORT" --user="$BACKUP_DB_USERNAME" \
  --single-transaction --triggers --routines --events --no-tablespaces \
  --default-character-set=utf8mb4 --result-file="$partial" "$DB_DATABASE"

test -s "$partial"
tail -c 4096 "$partial" | grep -F -- '-- Dump completed' >/dev/null
digest=$(sha256sum "$partial" | cut -d' ' -f1)
# Refer to the final basename, so verification also works after transferring it.
printf '%s  %s\n' "$digest" "$(basename "$final")" > "$partial.sha256"
mv -- "$partial" "$final"
mv -- "$partial.sha256" "$final.sha256"
chmod 600 -- "$final" "$final.sha256"
printf 'Backup created: %s\nChecksum: ' "$final"
cut -d' ' -f1 "$final.sha256"

