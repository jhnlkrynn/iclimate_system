#!/usr/bin/env bash
set -u
fail=0
check() { if command -v "$1" >/dev/null 2>&1; then printf 'PASS %-18s %s\n' "$1" "$(command -v "$1")"; else printf 'FAIL %-18s unavailable\n' "$1"; fail=1; fi; }
for c in php composer node npm python3 mariadb mariadb-dump nginx systemctl; do check "$c"; done
php -m 2>/dev/null | grep -Eq '^pdo_mysql$' && echo 'PASS pdo_mysql' || { echo 'FAIL pdo_mysql'; fail=1; }
php -m 2>/dev/null | grep -Eq '^mbstring$' && echo 'PASS mbstring' || { echo 'FAIL mbstring'; fail=1; }
php -m 2>/dev/null | grep -Eq '^openssl$' && echo 'PASS openssl' || { echo 'FAIL openssl'; fail=1; }
php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' && echo 'PASS PHP >= 8.2' || { echo 'FAIL PHP >= 8.2'; fail=1; }
test -f /etc/iclimate/iclimate.env && { test "$(stat -c %a /etc/iclimate/iclimate.env 2>/dev/null)" = 600 && echo 'PASS env permissions' || { echo 'FAIL env permissions'; fail=1; }; } || echo 'WARN env file not installed'
exit "$fail"

