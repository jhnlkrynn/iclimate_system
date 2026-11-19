# Glob-parser build mitigation

Advisory: [GHSA-fc5q-39xc-vjpf](https://github.com/advisories/GHSA-fc5q-39xc-vjpf), versions through 4.0.7; no published fixed version at verification on 2026-11-18.

The installed 2.3.2 and 4.0.7 parsers each failed a bounded child-process probe for `+(*a)` against 45 `a` characters followed by `!`. The process was killed after 600 ms. The application does not offer user-defined glob expressions; these packages are build/watch dependencies, not browser runtime code. Nevertheless the vulnerable build code is mitigated explicitly.

`npm ci` runs `scripts/security/harden-picomatch.mjs`. It verifies each installed parser against the version/source hashes in `picomatch-policy.json`, then rejects wildcard-containing repeated extglobs before a regex can be created. This also applies when the upstream recursion guard is disabled. Ordinary path globs and literal repeated alternatives remain supported. The restriction intentionally removes unsupported build expressions such as `+(*a)`; no project configuration needs them.

Both `npm run build` and `npm run dev` verify that every discovered parser contains exactly the mitigation before invoking Vite. Unrecognized versions, changed source, absent patches and duplicate/modified patch content fail verification. Never use an unverified manually modified dependency tree. `--ignore-scripts` installation alone is insufficient: the build refuses an unpatched tree.

`tests/js/picomatch-security.test.mjs` exercises the payload and variants in bounded child processes for every installed copy, verifies ordinary build patterns, and tests clean application/check/tamper rejection in a disposable tree. A fresh lockfile install, complete production build and browser suite must pass after any dependency change.

This is a project mitigation, not an upstream release or a claim that all regular expressions are safe. Keep the npm version identifiers and audit warnings intact. Raw `npm audit` continues to report the advisory against upstream tarballs. Do not exclude that advisory from reports or force incompatible dependency downgrades. Review and remove this policy only after a published upstream fix is independently tested. Build trusted sources in isolation and never expose the Vite development server as the production site.
