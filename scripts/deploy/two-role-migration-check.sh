#!/usr/bin/env bash
# Read-only snapshot / comparison for the 2026-11-16..17 two-role migrations (Phase 18).
#
#   scripts/deploy/two-role-migration-check.sh snapshot <dir>          record schema, counts, checksums, fingerprints
#   scripts/deploy/two-role-migration-check.sh compare <before> <after> prove existing data survived the migration
#
# Connects as the restricted application account from .env (SELECT only is used), so it can never change the
# database. The password is read from .env into MYSQL_PWD and is never printed or passed on the command line.
set -Eeuo pipefail
cd "$(dirname "$0")/../.."

env_value() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'; }
DB="${CHECK_DATABASE:-$(env_value DB_DATABASE)}"   # CHECK_DATABASE: rehearse against a clone
HOST="${CHECK_HOST:-$(env_value DB_HOST)}"; PORT="${CHECK_PORT:-$(env_value DB_PORT)}"; USER_NAME="${CHECK_USER:-$(env_value DB_USERNAME)}"
export MYSQL_PWD="${CHECK_PASSWORD:-$(env_value DB_PASSWORD)}"   # CHECK_*: rehearse against another server
m() { mysql -h"${HOST:-127.0.0.1}" -P"${PORT:-3306}" -u"$USER_NAME" --batch --skip-column-names "$@"; }

# Tables the migrations alter; their CHECKSUM changes when columns are added, so they are compared on the
# columns that existed before (fingerprint-columns.txt), not with CHECKSUM TABLE.
ALTERED="users notifications farmer_organizations"
UNTOUCHED="farmer_profiles farms rice_productions yield_predictions account_emails"
NEW_TABLES="association_chairman_assignments association_reporting_periods association_yield_reports association_yield_report_reviews association_reminder_deliveries association_messages association_chairman_invitations farmer_account_reviews"

snapshot() {
    local dir="$1"; mkdir -p "$dir"
    echo "$DB" > "$dir/database.txt"
    for t in $(m -e "select table_name from information_schema.tables where table_schema='$DB' and table_type='BASE TABLE' order by 1"); do
        echo "$t $(m "$DB" -e "select count(*) from \`$t\`")"
    done > "$dir/row-counts.txt"
    m -e "select table_name,column_name,column_type,is_nullable,ifnull(column_default,'NULL') from information_schema.columns where table_schema='$DB' order by 1,ordinal_position" > "$dir/schema-columns.tsv"
    m -e "select table_name,index_name,non_unique,group_concat(column_name order by seq_in_index) from information_schema.statistics where table_schema='$DB' group by 1,2,3 order by 1,2" > "$dir/schema-indexes.tsv"
    m -e "select constraint_name,table_name,column_name,referenced_table_name from information_schema.key_column_usage where table_schema='$DB' and referenced_table_name is not null order by 2,1" > "$dir/schema-foreign-keys.tsv"
    m "$DB" -e "CHECKSUM TABLE $(echo $UNTOUCHED | sed 's/ /, /g')" | awk '{sub(/^[^.]*\./, "", $1); print $1, $2}' > "$dir/checksums-untouched.txt"
    m "$DB" -e "select role,status,count(*) from users group by 1,2" | LC_ALL=C sort > "$dir/users-by-role-status.txt"
    m "$DB" -e "select migration from migrations order by id" > "$dir/migrations.txt"
    # Column list is fixed at the first snapshot so the "after" fingerprint hashes exactly the same columns.
    if [ ! -f "$dir/fingerprint-columns.txt" ]; then
        for t in $ALTERED; do
            echo "$t|$(m -e "select group_concat(concat('\`',column_name,'\`') order by ordinal_position) from information_schema.columns where table_schema='$DB' and table_name='$t'")"
        done > "$dir/fingerprint-columns.txt"
    fi
    fingerprints "$dir/fingerprint-columns.txt" > "$dir/column-fingerprints.txt"
    echo "snapshot of $DB in $dir: $(wc -l < "$dir/row-counts.txt" | tr -d ' ') tables, $(awk '{s+=$2} END{print s}' "$dir/row-counts.txt") rows, $(wc -l < "$dir/migrations.txt" | tr -d ' ') migrations"
}

fingerprints() {
    while IFS='|' read -r t cols; do
        echo "$t $(m "$DB" -e "select concat(count(*),' ',ifnull(sum(crc32(concat_ws('|',$cols))),0)) from \`$t\`")"
    done < "$1"
}

compare() {
    local before="$1" after="$2" failed=0
    [ "$(cat "$before/database.txt")" = "$(cat "$after/database.txt")" ] || { echo "FAIL: snapshots are of different databases"; exit 1; }
    # Recompute the "after" fingerprints over the BEFORE column list (new columns excluded).
    fingerprints "$before/fingerprint-columns.txt" > "$after/column-fingerprints-on-before-columns.txt"
    check() { if diff -q "$1" "$2" >/dev/null; then echo "OK    $3"; else echo "FAIL  $3"; diff "$1" "$2" | head -10 || true; failed=1; fi; }
    check "$before/users-by-role-status.txt" "$after/users-by-role-status.txt" "users by role/status unchanged"
    check "$before/column-fingerprints.txt" "$after/column-fingerprints-on-before-columns.txt" "existing columns of users/notifications/farmer_organizations unchanged"
    check "$before/checksums-untouched.txt" "$after/checksums-untouched.txt" "checksums of untouched agricultural tables unchanged"
    # Every pre-existing table keeps its row count (new tables are expected and must be empty).
    join <(sort "$before/row-counts.txt") <(sort "$after/row-counts.txt") | awk '$1 != "migrations" && $2 != $3 {print "      row count changed:", $1, $2, "->", $3}' > "$after/row-count-drift.txt"
    if [ -s "$after/row-count-drift.txt" ]; then echo "WARN  row counts changed (expected only if the app was not in maintenance):"; cat "$after/row-count-drift.txt"; else echo "OK    row counts of all pre-existing tables unchanged"; fi
    local new_ok=1
    for t in $NEW_TABLES; do
        n=$(awk -v t="$t" '$1==t {print $2}' "$after/row-counts.txt")
        if [ "$n" != "0" ]; then echo "FAIL  new table $t missing or not empty (${n:-missing})"; failed=1; new_ok=0; fi
    done
    if [ $new_ok = 1 ]; then echo "OK    all 8 new tables exist and are empty"; fi
    added=$(comm -13 "$before/migrations.txt" "$after/migrations.txt")
    expected=$'2026_11_16_000001_add_association_directory_fields\n2026_11_16_000002_create_association_reporting_tables\n2026_11_16_000003_allow_unknown_association_farm_type\n2026_11_16_000004_create_association_chairman_invitations_table\n2026_11_17_000001_allow_canonical_application_roles\n2026_11_17_000002_add_farmer_account_review_history'
    if [ "$added" = "$expected" ]; then echo "OK    exactly the 6 approved migrations were recorded"; else echo "FAIL  migrations recorded differ from the approved six:"; echo "$added"; failed=1; fi
    comm -23 <(sort "$before/schema-foreign-keys.tsv") <(sort "$after/schema-foreign-keys.tsv") > "$after/foreign-keys-lost.txt"
    if [ -s "$after/foreign-keys-lost.txt" ]; then echo "FAIL  foreign keys lost:"; cat "$after/foreign-keys-lost.txt"; failed=1; else echo "OK    no existing foreign key lost ($(comm -13 <(sort "$before/schema-foreign-keys.tsv") <(sort "$after/schema-foreign-keys.tsv") | wc -l | tr -d ' ') added)"; fi
    exit $failed
}

case "${1:-}" in
    snapshot) snapshot "${2:?snapshot directory required}" ;;
    compare) compare "${2:?before directory required}" "${3:?after directory required}" ;;
    *) sed -n '2,8p' "$0"; exit 2 ;;
esac
