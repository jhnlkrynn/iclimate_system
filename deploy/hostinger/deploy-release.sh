#!/usr/bin/env bash
# iClimate — install the approved release on the VPS from a git bundle (no push to any remote needed).
# Run as the operator, after provision-ubuntu-24.04.sh:
#
#   TAG=<approved-tag> EXPECTED_COMMIT=<approved-commit> EXPECTED_FINGERPRINT=<approved-fingerprint> \
#     bash deploy-release.sh
#
# VERIFY_ONLY=1 stops after the release checks (used to test this script away from the server).
# It never migrates, never touches the database and never starts or restarts a service.
set -Eeuo pipefail
umask 022
: "${TAG:?approved annotated release tag required}"
: "${EXPECTED_COMMIT:?approved release commit required}"
: "${EXPECTED_FINGERPRINT:?approved release fingerprint required}"
EXPECTED_MODEL_SHA="${EXPECTED_MODEL_SHA:-f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c}"
APP="${APP:-/var/www/iclimate_system}"
ENV_FILE="${ENV_FILE:-/etc/iclimate/iclimate.env}"
: "${BUNDLE:?BUNDLE (git bundle of the release) required}"; : "${MANIFEST:?MANIFEST (approved release manifest) required}"
ok() { echo "OK    $1"; }; die() { echo "FAIL  $1"; exit 1; }

# 1. The manifest is the approved one.
fingerprint=$(grep -v '^#' "$MANIFEST" | sha256sum 2>/dev/null | cut -d' ' -f1 || grep -v '^#' "$MANIFEST" | shasum -a 256 | cut -d' ' -f1)
[ "$fingerprint" = "$EXPECTED_FINGERPRINT" ] && ok "manifest fingerprint $fingerprint" || die "manifest fingerprint $fingerprint is not the approved one"

# 2. The bundle holds the approved tag and commit.
BUNDLE="$(cd "$(dirname "$BUNDLE")" && pwd)/$(basename "$BUNDLE")"
# A fresh VPS has no enclosing repository. Verify in an empty temporary repository,
# which also refuses a thin bundle that relies on commits already present locally.
bundle_check=$(mktemp -d)
trap 'rm -rf -- "$bundle_check"' EXIT
git init -q --bare "$bundle_check"
git -C "$bundle_check" bundle verify -q "$BUNDLE" >/dev/null 2>&1 && ok "bundle is complete" || die "bundle cannot be verified"
rm -rf -- "$bundle_check"
trap - EXIT
bundle_tag=$(git bundle list-heads "$BUNDLE" "refs/tags/$TAG" | cut -d' ' -f1)
[ -n "$bundle_tag" ] || die "bundle does not contain $TAG"
fresh=0
if [ ! -d "$APP/.git" ]; then
    [ -z "$(ls -A "$APP" 2>/dev/null)" ] || die "$APP is not empty and not a git checkout"
    git clone -q --no-checkout "$BUNDLE" "$APP"
    fresh=1
fi
git -C "$APP" fetch -q "$BUNDLE" "refs/tags/$TAG:refs/tags/$TAG" || die "could not fetch the release tag; destination preserved"
[ "$(git -C "$APP" rev-parse "refs/tags/$TAG")" = "$bundle_tag" ] || die "local tag differs from the bundle"
commit=$(git -C "$APP" rev-parse "$TAG^{commit}")
[ "$commit" = "$EXPECTED_COMMIT" ] && ok "tag $TAG is commit $commit" || die "tag $TAG points at $commit"
[ "$(git -C "$APP" cat-file -t "$TAG")" = tag ] && ok "annotated tag" || die "$TAG is not an annotated tag"
# A checkout that was changed on the server is reported, never silently overwritten.
if [ "$fresh" = 0 ] && [ -n "$(git -C "$APP" status --porcelain --untracked-files=all)" ]; then
    git -C "$APP" status --short --untracked-files=all | head -10
    die "files in $APP were changed on the server; review them before deploying"
fi
git -C "$APP" -c advice.detachedHead=false checkout -q --no-overwrite-ignore "$TAG" || die "checkout refused; inspect unexpected files"

# 3. Every file is exactly the approved content, nothing missing, nothing extra.
python3 - "$APP" "$MANIFEST" <<'EOF' || die "checked-out files differ from the manifest"
import hashlib, subprocess, sys
app, manifest = sys.argv[1], sys.argv[2]
want = {}
for line in open(manifest):
    if line.startswith('#') or line.startswith('REMOVED') or not line.strip():
        continue
    sha, path = line.rstrip('\n').split('  ', 1)
    want[path] = sha
tracked = [p for p in subprocess.run(['git', '-C', app, 'ls-files', '-z'], capture_output=True, check=True).stdout.decode().split('\0') if p]
bad = [p for p in tracked if p not in want or hashlib.sha256(open(f'{app}/{p}', 'rb').read()).hexdigest() != want[p]]
missing = [p for p in want if p not in set(tracked)]
print(f'      {len(tracked)} files checked; {len(bad)} differ; {len(missing)} missing')
sys.exit(1 if bad or missing else 0)
EOF
ok "all files match the approved manifest"
model=$(sha256sum "$APP/storage/models/rice_yield_model_final.pkl" 2>/dev/null | cut -d' ' -f1 || shasum -a 256 "$APP/storage/models/rice_yield_model_final.pkl" | cut -d' ' -f1)
[ "$model" = "$EXPECTED_MODEL_SHA" ] && ok "deployed model checksum" || die "model checksum $model"
[ "${VERIFY_ONLY:-0}" = 1 ] && { echo "VERIFY_ONLY: release checks passed; nothing installed."; exit 0; }

# 4. Production settings are safe (values are never printed).
[ -r "$ENV_FILE" ] || die "$ENV_FILE missing or unreadable"
# Laravel reads the FIRST definition of a key; a duplicate could pass these checks with a different live value.
dupes=$(grep -E '^[A-Za-z_][A-Za-z0-9_]*=' "$ENV_FILE" | cut -d= -f1 | sort | uniq -d | tr '\n' ' ')
[ -z "$dupes" ] || die "duplicate settings in $ENV_FILE (keep one of each): $dupes"
val() { grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"'; }
[ "$(val APP_ENV)" = production ] && [ "$(val APP_DEBUG)" = false ] && ok "APP_ENV=production, APP_DEBUG=false" || die "set APP_ENV=production and APP_DEBUG=false"
[ "$(val ICLIMATE_PASSWORD_LOCKDOWN)" = true ] && ok "password lockdown on" || die "ICLIMATE_PASSWORD_LOCKDOWN must be true in production"
case "$(val ICLIMATE_YIELD_GATE)" in
    withhold|label) ok "yield gate $(val ICLIMATE_YIELD_GATE)" ;;
    *) die "ICLIMATE_YIELD_GATE must be withhold or label (ACCURACY NOT VERIFIED; off only after validation)" ;;
esac
case "$(val ICLIMATE_YIELD_ACCURACY_VERIFIED)" in
    ''|false|0) ok "yield accuracy not verified: estimates are withheld or labelled unvalidated" ;;
    *) die "ICLIMATE_YIELD_ACCURACY_VERIFIED must be false until the owner approves an independent validation" ;;
esac
[ "$(val DB_CONNECTION)" = mysql ] && ok "database driver mysql (MariaDB 10.11)" || die "DB_CONNECTION must be mysql: migration steps guarded for MySQL/MariaDB are skipped under the mariadb driver"
[ "$(val SESSION_SECURE_COOKIE)" = true ] && ok "secure cookies" || die "SESSION_SECURE_COOKIE must be true"
[ "$(val BROADCAST_CONNECTION)" = reverb ] || die "BROADCAST_CONNECTION must be reverb"
[ -n "$(val REVERB_APP_KEY)" ] && [ "$(val VITE_REVERB_APP_KEY)" = "$(val REVERB_APP_KEY)" ] || die "VITE_REVERB_APP_KEY must match REVERB_APP_KEY before building"
[ -n "$(val APP_KEY)" ] && ok "APP_KEY set" || die "APP_KEY missing (php artisan key:generate --show, then store it in the env file)"
ICLIMATE_CHECK_KEY="$(val APP_KEY)" python3 - <<'EOF' || die "APP_KEY must be a generated base64 Laravel AES-256 key"
import base64, os, sys
key = os.environ['ICLIMATE_CHECK_KEY']
try:
    assert key.startswith('base64:') and len(base64.b64decode(key[7:], validate=True)) == 32
except Exception:
    sys.exit(1)
EOF
! grep -qE '^[A-Z0-9_]+=.*\$\{' "$ENV_FILE" && ok "no \${...} references (systemd EnvironmentFile= may not expand them)" || die "replace every \${...} in $ENV_FILE with its value"
! grep -qE '^[A-Z0-9_]+=REPLACE_WITH_' "$ENV_FILE" && ok "no template placeholders left" || die "fill every REPLACE_WITH_ value in $ENV_FILE"
python3 - "$ENV_FILE" <<'EOF' || die "environment file permissions are unsafe"
import os, stat, sys
mode = stat.S_IMODE(os.stat(sys.argv[1]).st_mode)
sys.exit(1 if mode & 0o027 else 0)  # no group write or any access by others
EOF
if [ -e "$APP/.env" ] || [ -L "$APP/.env" ]; then
    [ -L "$APP/.env" ] && [ "$APP/.env" -ef "$ENV_FILE" ] || die "existing .env must be backed up and reviewed before installing"
else
    ln -s "$ENV_FILE" "$APP/.env"
fi

# 5. Dependencies from the release's lockfiles only.
cd "$APP"
php -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' || die "PHP 8.4.1+ required by composer.lock"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
npm ci --no-fund && npm run build && ok "frontend built"
[ -d .venv-ml ] || python3 -m venv .venv-ml
.venv-ml/bin/pip install -q --upgrade pip && .venv-ml/bin/pip install -q -r python_scripts/requirements.txt
.venv-ml/bin/python -c 'import sklearn; assert sklearn.__version__ == "1.6.1", sklearn.__version__' && ok "scikit-learn 1.6.1"

# 6. Writable folders for PHP-FPM; nothing else is writable by the web user.
sudo setfacl -R -m g:www-data:rwX -m d:g:www-data:rwX storage bootstrap/cache
ok "release $TAG installed in $APP. Next: database (handoff steps 3-5), then php artisan config:cache route:cache view:cache, then services."
