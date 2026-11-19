# RC2-R2 commit and tag procedure: run only after explicit owner authorisation

This is checkpoint 1 in `CHECKPOINTS.md`. Nothing below has been executed.

**Rehearsed 2026-11-18** with a temporary index and a scratch object store: no ref, commit or real index entry was created. The 1,504 approved paths produce exactly tree **`d823c0146ebc7e0448285b4105918faf6e920862`**: 42 added and 48 modified, and every file's sha256 equals the manifest.

| Item | Value |
|---|---|
| Version / proposed tag | `2026.11.18-rc2` / `v2026.11.18-rc2` (does not exist yet) |
| Base | RC1 `d43cd1aa1b3915b2b2e2928e21b207b3346f1170` (annotated tag `v2026.11.18-rc1`, unchanged) |
| Source fingerprint | `04c7db9fedf0db9c077521adfebae8621291b1ddcf5e0e371a72a966dcb2d29d` |
| Archive sha256 | `c26275e0d6cbde76d5c3582afd9d637f23f992408dddd815ffd5aca2e24527ad` |
| Expected commit tree | `d823c0146ebc7e0448285b4105918faf6e920862` |
| Files | 1,504 (`approved-paths.txt`) |
| Left untracked on purpose | `storage/models/experiments/`, `storage/datasets/external/`, `docs/deployment/releases/`, `docs/deployment/ICLIMATE_RC2_FINAL_READINESS.md` |

The superseded `../2026.11.18-rc2/` must not be used. Its `APPROVAL.md` is out of date.

## Procedure

Each step stops on failure.

```sh
cd /Users/johnlukereynon/iclimate_system
REL=docs/deployment/releases/2026.11.18-rc2-r2

# 0. Preconditions: still RC1, nothing staged, no RC2 tag, source unchanged since the build
test "$(git rev-parse HEAD)" = d43cd1aa1b3915b2b2e2928e21b207b3346f1170
git diff --cached --quiet
test -z "$(git tag -l v2026.11.18-rc2)"
.venv-ml/bin/python scripts/deploy/prepare-release.py verify-tree --manifest "$REL/source.manifest"
(cd "$REL" && shasum -a 256 -c source.manifest.sha256 source.tar.gz.sha256)

# 1. A branch named for RC2 (the current branch is release/2026.11.18-rc1); the working tree is kept as is
git switch -c release/2026.11.18-rc2

# 2. Stage exactly the approved paths. Never `git add .` or `-A`.
git add --pathspec-from-file="$REL/approved-paths.txt"
git diff --cached --name-status | cut -f1 | sort | uniq -c      # expect: 42 A, 48 M, nothing else
# Whitespace check. The diff file is excluded: blank context lines in a diff are a single space by design.
git diff --cached --check -- . ':(exclude)docs/deployment/evidence/PredictionEngine-frozen-inputs.patch'
test "$(git write-tree)" = d823c0146ebc7e0448285b4105918faf6e920862   # the rehearsed tree
# Review the staged diff before continuing: git diff --cached

# 3. Commit
git commit -F - <<'MSG'
Release 2026.11.18-rc2: frozen ML input fix, report integrity, deployment safety

Source fingerprint 04c7db9fedf0db9c077521adfebae8621291b1ddcf5e0e371a72a966dcb2d29d.
Yield gate withhold, ICLIMATE_YIELD_ACCURACY_VERIFIED=false. ACCURACY NOT VERIFIED.
Model sha256 f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c unchanged.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
MSG
test "$(git rev-parse 'HEAD^{tree}')" = d823c0146ebc7e0448285b4105918faf6e920862
test -z "$(git status --porcelain | grep -v '^??')"              # nothing tracked is left uncommitted
git status --porcelain | cut -c4- | cut -d/ -f1-3 | sort -u       # only the four untracked exclusions above

# 4. Tag, and record the commit (outside the source payload)
git tag -a v2026.11.18-rc2 -F "$REL/tag-annotation.txt"
git rev-parse 'v2026.11.18-rc2^{commit}' > "$REL/APPROVED-COMMIT.txt"
git verify-tag v2026.11.18-rc2 2>/dev/null || git cat-file -t v2026.11.18-rc2     # "tag" = annotated

# 5. A complete bundle for the private VPS; nothing is pushed
git bundle create /path/outside/repo/iclimate-v2026.11.18-rc2.bundle v2026.11.18-rc2
git bundle verify /path/outside/repo/iclimate-v2026.11.18-rc2.bundle
```

## After tagging

- Pass the tag, the commit (`APPROVED-COMMIT.txt`) and the fingerprint explicitly to `deploy-release.sh`. See `VPS-CHECKLIST.md`.
- Any source change after this build requires a new candidate directory and a new fingerprint. Never edit `source.manifest`.
- Pushing to `origin` (GitHub) and merging into `main` are separate decisions. Neither is part of this checkpoint.
