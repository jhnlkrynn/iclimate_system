#!/usr/bin/env bash
# Catches the mistakes a bad merge leaves behind before they reach a page as a 500 error:
#   1. leftover conflict markers (<<<<<<< / >>>>>>>)
#   2. PHP syntax errors
#   3. classes that fail to load (e.g. a method clashing with its parent), and Blade templates that do not compile to valid PHP (a stray @endif, an unclosed @if, ...)
#
# Usage:
#   scripts/check-code.sh            check every tracked file (CI, `composer check`)
#   scripts/check-code.sh --staged   check only staged files (the git hooks)
#
# PHP: uses $PHP if set, else `php` on PATH (PHP 8.2+, as composer.json requires).
set -uo pipefail

cd "$(git rev-parse --show-toplevel)" || exit 1
PHP="${PHP:-php}"
failed=0

if ! command -v "$PHP" >/dev/null 2>&1; then
    echo "✗ PHP not found (\"$PHP\"). Put PHP 8.2+ on PATH, or set PHP to its path, e.g. PHP=/path/to/php"
    exit 1
fi

if [ "${1:-}" = "--staged" ]; then
    files=$(git diff --cached --name-only --diff-filter=ACMR)
else
    files=$(git ls-files)
fi
files=$(printf '%s\n' "$files" | grep -vE '^(vendor|node_modules|public/build|storage)/' | while read -r f; do [ -f "$f" ] && echo "$f"; done)

# `php -l` takes many files at once (PHP 8.3+): one PHP start-up per 200 files. Prints only the error lines.
lint() {
    xargs -r -n 200 "$PHP" -n -d display_errors=stderr -d log_errors=0 -l 2>&1 >/dev/null | grep -iE 'error' | sort -u
}

# 1. Conflict markers (source files only; markdown may legitimately show them).
markers=$(printf '%s\n' "$files" | grep -E '\.(php|js|mjs|css|json|py|ya?ml)$' | xargs -r grep -nE '^(<<<<<<<|>>>>>>>)( |$)|^=======$' /dev/null)
if [ -n "$markers" ]; then
    echo "✗ Merge conflict markers left in:"
    echo "$markers"
    failed=1
fi

# 2. PHP syntax (Blade files are checked in step 3).
errors=$(printf '%s\n' "$files" | grep -E '\.php$' | grep -v '\.blade\.php$' | lint)
if [ -n "$errors" ]; then
    echo "✗ PHP syntax errors:"
    echo "$errors"
    failed=1
fi

# 2b. Load every class in app/: catches what a syntax check cannot, such as a method that clashes with its parent's
#     (PHP refuses to load the class, so every page using it is a 500).
if [ "${1:-}" != "--staged" ] || printf '%s\n' "$files" | grep -q '^app/.*\.php$'; then
    errors=$("$PHP" -d display_errors=stderr -r '
        require "vendor/autoload.php";
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("app", FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== "php") continue;
            $class = "App\\".str_replace(["/", "\\\\", ".php"], ["\\", "\\", ""], substr($file->getPathname(), 4));
            try { class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class); }
            catch (Throwable $e) { echo $class, ": ", $e->getMessage(), PHP_EOL; }
        }' 2>&1 | grep -v '^$')
    if [ -n "$errors" ]; then
        echo "✗ Classes that fail to load:"
        echo "$errors"
        failed=1
    fi
fi

# 3. Blade: compile every view into a scratch folder, then syntax-check the compiled PHP.
if [ "${1:-}" != "--staged" ] || printf '%s\n' "$files" | grep -q '\.blade\.php$'; then
    compiled=$(mktemp -d)
    if ! VIEW_COMPILED_PATH="$compiled" "$PHP" artisan view:cache >/dev/null 2>&1; then
        echo "✗ Blade templates failed to compile (run: php artisan view:cache)"
        failed=1
    fi
    errors=$(find "$compiled" -name '*.php' | lint)
    if [ -n "$errors" ]; then
        # Name the template each broken file came from (Blade appends /**PATH <template> ENDPATH**/ to it).
        for c in $(printf '%s\n' "$errors" | grep -oE '[^ ]+\.php' | sort -u); do
            [ -f "$c" ] || continue
            view=$(grep -o 'PATH .* ENDPATH' "$c" | head -1 | sed 's/^PATH //; s/ ENDPATH$//')
            echo "✗ Blade syntax error in ${view:-$c}:"
            printf '%s\n' "$errors" | grep -F "$c" | sed "s#$c#(compiled)#"
        done
        failed=1
    fi
    rm -rf "$compiled"
fi

if [ "$failed" -ne 0 ]; then
    echo
    echo "Fix the problems above before committing. (A merge that kept both sides of a conflict is the usual cause.)"
    exit 1
fi
echo "✓ No conflict markers, PHP syntax errors, classes that fail to load or broken Blade templates."
