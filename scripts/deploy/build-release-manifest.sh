#!/usr/bin/env bash
# Release candidate manifest (read-only): every file the release contains, with its sha256.
#   scripts/deploy/build-release-manifest.sh [output-file]
# Contents = tracked files as they are now (deleted ones are listed as removals) + untracked, not-ignored files,
# minus research-only artifacts (they are not read at runtime; commit them separately if wanted).
# Fails if a file that must never ship (env, credentials, backups, logs, caches, dumps) would be included.
set -Eeuo pipefail
cd "$(dirname "$0")/../.."
out="${1:-/dev/stdout}"

research='^(storage/models/experiments/|storage/datasets/external/)'
forbidden='(^|/)\.env($|\.)|\.env\.(local|production)$|^storage/(app/backups|logs|framework)/|^bootstrap/cache/|\.(sql\.gz|dump|sqlite|log)$|(^|/)\.DS_Store$|^vendor/|^node_modules/|^\.venv'

files=$( { git ls-files; git ls-files --others --exclude-standard; } | sort -u | grep -vE "$research" || true)
# .gitignore placeholders that keep empty storage/cache folders are fine. A file that is ignored by the rules but
# still tracked (committed before the rule existed) is listed for `git rm --cached` instead of shipping.
untrack=$(git ls-files -ci --exclude-standard || true)
files=$(printf '%s\n' "$files" | grep -vxF -f <(printf '%s\n' "$untrack" | sed '/^$/d') || true)
blocked=$(printf '%s\n' "$files" | grep -E "$forbidden" | grep -vE '\.env\.example$|(^|/)\.gitignore$' || true)
if [ -n "$blocked" ]; then
    echo "REFUSED: files that must never ship would be included:" >&2
    printf '  %s\n' $blocked >&2
    exit 1
fi

{
    echo "# iClimate release manifest ${RELEASE_VERSION:-unversioned} — base $(git rev-parse --short HEAD), generated $(date -u +%Y-%m-%dT%H:%M:%SZ)"
    echo "# research-only artifacts excluded: $( { git ls-files --others --exclude-standard; } | grep -cE "$research" || true)"
    printf '%s\n' "$untrack" | sed '/^$/d' | sed 's/^/# UNTRACK (git rm --cached): /'
    printf '%s\n' "$files" | while IFS= read -r f; do
        if [ -f "$f" ]; then shasum -a 256 "$f"; else echo "REMOVED  $f"; fi
    done
} > "$out"
if [ "$out" != /dev/stdout ]; then
    fingerprint=$(grep -v '^#' "$out" | shasum -a 256 | cut -d' ' -f1)
    echo "$fingerprint  ${RELEASE_VERSION:-unversioned}" > "$out.sha256"
    echo "manifest: $out ($(grep -vc '^#' "$out") entries, $(grep -c '^REMOVED' "$out") removals); fingerprint $fingerprint"
fi
