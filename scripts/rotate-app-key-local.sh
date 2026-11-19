#!/usr/bin/env bash
# Rotate the local APP_KEY without breaking anything it protects (encrypted session cookies, the cached user entries).
# Nothing in the database is encrypted with APP_KEY. Keys are never printed.
#
#   bash scripts/rotate-app-key-local.sh --rehearse   prove old-key data decrypts under new key + APP_PREVIOUS_KEYS (no change)
#   bash scripts/rotate-app-key-local.sh --apply      private copy of .env, set APP_KEY=new and APP_PREVIOUS_KEYS=old, verify,
#                                                     restart the local services
#   bash scripts/rotate-app-key-local.sh --finalize   after the session lifetime (120 min) has passed: drop APP_PREVIOUS_KEYS
set -Eeuo pipefail
umask 077

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"
BACKUP_DIR="$HOME/.iclimate-private/env-backups"
cd "$PROJECT_ROOT"
fail() { printf 'FAIL: %s\n' "$1" >&2; exit 1; }
mode="${1:-}"
[ -f "$ENV_FILE" ] || fail ".env not found."

env_value() { { grep -E "^$1=" "$ENV_FILE" || true; } | tail -n 1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/; s/^'"'"'(.*)'"'"'$/\1/'; }

# Encrypts a probe with the given key; decrypts with the given key set. Values travel only in the environment.
encrypt_with() { PROBE_KEY="$1" php -r 'require "vendor/autoload.php"; $k = getenv("PROBE_KEY"); $k = str_starts_with($k, "base64:") ? base64_decode(substr($k, 7)) : $k; echo (new Illuminate\Encryption\Encrypter($k, "AES-256-CBC"))->encryptString("iclimate-rotation-probe");'; }
decrypts_with_app() { CIPHERTEXT="$1" php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); try { exit(Illuminate\Support\Facades\Crypt::decryptString(getenv("CIPHERTEXT")) === "iclimate-rotation-probe" ? 0 : 1); } catch (Throwable) { exit(1); }'; }

set_line() { # set_line KEY VALUE: replace or append, value single-quoted (keys contain no quote)
  local key="$1" value="$2" tmp
  tmp="$(mktemp)"
  if grep -qE "^$key=" "$ENV_FILE"; then
    KEY="$key" VALUE="$value" awk 'BEGIN{k=ENVIRON["KEY"]; v=ENVIRON["VALUE"]} index($0, k"=")==1 && !d {print k"=\047"v"\047"; d=1; next} {print}' "$ENV_FILE" >"$tmp"
  else
    cp "$ENV_FILE" "$tmp"
    printf "%s='%s'\n" "$key" "$value" >>"$tmp"
  fi
  cat "$tmp" >"$ENV_FILE"
  rm -f "$tmp"
  chmod 600 "$ENV_FILE"
}

old_key="$(env_value APP_KEY)"
[ -n "$old_key" ] || fail "APP_KEY is not set."

case "$mode" in
  --rehearse)
    new_key="$(php artisan key:generate --show --no-interaction 2>/dev/null)"
    [ -n "$new_key" ] && [ "$new_key" != "$old_key" ] || fail "Could not generate a new key."
    probe="$(encrypt_with "$old_key")"
    APP_KEY="$new_key" APP_PREVIOUS_KEYS="$old_key" decrypts_with_app "$probe" && echo "PASS: data encrypted with the current key still decrypts after rotation (via APP_PREVIOUS_KEYS)."
    APP_KEY="$new_key" APP_PREVIOUS_KEYS= decrypts_with_app "$probe" && fail "Unexpected: old data decrypted without the previous key." || echo "PASS: without APP_PREVIOUS_KEYS old data is rejected, so the previous key is required during the transition."
    newprobe="$(encrypt_with "$new_key")"
    APP_KEY="$new_key" APP_PREVIOUS_KEYS="$old_key" decrypts_with_app "$newprobe" && echo "PASS: new data uses the new key."
    echo "Rehearsal only: .env was not changed."
    ;;
  --apply)
    mkdir -p "$BACKUP_DIR" && chmod 700 "$BACKUP_DIR"
    copy="$BACKUP_DIR/.env.before-app-key-rotation-$(date -u +%Y%m%dT%H%M%SZ)"
    cp -p "$ENV_FILE" "$copy" && chmod 600 "$copy"
    probe="$(encrypt_with "$old_key")"
    new_key="$(php artisan key:generate --show --no-interaction 2>/dev/null)"
    [ -n "$new_key" ] && [ "$new_key" != "$old_key" ] || fail "Could not generate a new key; .env unchanged."
    previous="$(env_value APP_PREVIOUS_KEYS)"
    set_line APP_PREVIOUS_KEYS "${old_key}${previous:+,$previous}"
    set_line APP_KEY "$new_key"
    php artisan config:clear >/dev/null
    if ! decrypts_with_app "$probe"; then
      cp -p "$copy" "$ENV_FILE"
      fail "Verification failed; the previous .env was restored."
    fi
    echo "APP_KEY rotated. Old-key sessions stay valid through APP_PREVIOUS_KEYS. Private copy: $copy"
    bash scripts/start-iclimate-local.sh status >/dev/null 2>&1 && bash scripts/start-iclimate-local.sh restart | tail -n 8 || echo "Local services were not running; start them with scripts/start-iclimate-local.sh start."
    echo "After $(php artisan tinker --execute='echo config("session.lifetime");' 2>/dev/null || echo 120) minutes run: bash scripts/rotate-app-key-local.sh --finalize"
    ;;
  --finalize)
    [ -n "$(env_value APP_PREVIOUS_KEYS)" ] || { echo "APP_PREVIOUS_KEYS is already empty."; exit 0; }
    cp -p "$ENV_FILE" "$BACKUP_DIR/.env.before-app-key-finalize-$(date -u +%Y%m%dT%H%M%SZ)"
    tmp="$(mktemp)"; grep -vE '^APP_PREVIOUS_KEYS=' "$ENV_FILE" >"$tmp"; cat "$tmp" >"$ENV_FILE"; rm -f "$tmp"; chmod 600 "$ENV_FILE"
    php artisan config:clear >/dev/null
    echo "APP_PREVIOUS_KEYS removed. Restart services: bash scripts/start-iclimate-local.sh restart"
    ;;
  *)
    fail 'Usage: scripts/rotate-app-key-local.sh --rehearse | --apply | --finalize'
    ;;
esac
