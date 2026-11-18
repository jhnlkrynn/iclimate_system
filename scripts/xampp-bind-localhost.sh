#!/usr/bin/env bash
# Restrict XAMPP MariaDB to this Mac (bind-address=127.0.0.1). Dry run unless --apply is given.
#
#   bash scripts/xampp-bind-localhost.sh            # show the planned change and current exposure
#   bash scripts/xampp-bind-localhost.sh --apply    # back up my.cnf, add the line, restart MariaDB, verify (sudo)
#
# phpMyAdmin and the mysql client use the Unix socket and keep working; iClimate uses 127.0.0.1.
# To undo: restore the printed my.cnf backup and run `sudo /Applications/XAMPP/xamppfiles/xampp restartmysql`.
set -Eeuo pipefail

XAMPP_ROOT="${ICLIMATE_XAMPP_ROOT:-/Applications/XAMPP/xamppfiles}"
CNF="$XAMPP_ROOT/etc/my.cnf"
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APPLY=0
[ "${1:-}" = --apply ] && APPLY=1

fail() { printf 'FAIL: %s\n' "$1" >&2; exit 1; }
[ -f "$CNF" ] || fail "$CNF not found."

listening() { netstat -an -p tcp | awk '$4 ~ /\.3306$/ && $6 == "LISTEN" {print $1, $4}'; }
lan_ip="$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || true)"
lan_reachable() { [ -n "$lan_ip" ] && nc -z -G 2 "$lan_ip" 3306 >/dev/null 2>&1; }

echo "Current listener(s) on 3306: $(listening | paste -sd';' -)"
if lan_reachable; then echo "Reachable from the LAN at $lan_ip:3306: YES"; else echo "Reachable from the LAN: no"; fi
echo "Peers connected now: $(netstat -an -p tcp | awk '$4 ~ /\.3306$/ && $6 == "ESTABLISHED" {split($5, a, "."); print a[1]"."a[2]"."a[3]"."a[4]}' | sort -u | paste -sd' ' -)"

if awk '/^\[mysqld\]/{s=1; next} /^\[/{s=0} s && /^[[:space:]]*bind-address[[:space:]]*=[[:space:]]*127\.0\.0\.1/{found=1} END{exit !found}' "$CNF"; then
  echo "my.cnf already has bind-address=127.0.0.1 in [mysqld]."
  [ "$APPLY" = 1 ] || exit 0
else
  echo "Planned change: add 'bind-address=127.0.0.1' directly below 'port=3306' in the [mysqld] section of $CNF."
  if [ "$APPLY" = 0 ]; then
    echo "Dry run only. Run with --apply after approval (needs sudo; restarts MariaDB for a few seconds)."
    exit 0
  fi
  backup="$CNF.before-bind-$(date -u +%Y%m%dT%H%M%SZ)"
  sudo cp -p "$CNF" "$backup"
  tmp="$(mktemp)"
  awk '{print} /^\[mysqld\]/{s=1; next} /^\[/{s=0} s && !done && /^[[:space:]]*port[[:space:]]*=/{print "bind-address=127.0.0.1"; done=1}' "$CNF" >"$tmp"
  grep -q '^bind-address=127.0.0.1$' "$tmp" || { rm -f "$tmp"; fail "Could not place the line; my.cnf left unchanged (backup: $backup)."; }
  sudo cp "$tmp" "$CNF"
  rm -f "$tmp"
  echo "my.cnf updated; previous copy kept at $backup"
fi

echo "Restarting XAMPP MariaDB..."
sudo "$XAMPP_ROOT/xampp" restartmysql >/dev/null
for _ in $(seq 1 30); do listening | grep -q '127\.0\.0\.1\.3306' && break; sleep 1; done

echo "Listener(s) now: $(listening | paste -sd';' -)"
listening | grep -q '127\.0\.0\.1\.3306' || fail "MariaDB is not listening on 127.0.0.1:3306 after the restart. Restore the backup copy of my.cnf and restart."
if lan_reachable; then fail "Port 3306 is still reachable at $lan_ip."; fi
echo "LAN access to 3306: closed"
cd "$PROJECT_ROOT" && php artisan iclimate:environment-check | tail -1
