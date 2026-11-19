# Proposed RC2 approval — do not execute without explicit authorization

The source manifest, source archive and release.json describe one proposed source tree. The approved commit is deliberately null: no commit or tag was created. The final readiness report and this directory are companion evidence, excluded from the source payload to avoid a circular fingerprint.

After the owner explicitly authorizes committing and tagging this candidate:

1. Review the final readiness report, source changes and glob-parser mitigation. Confirm HEAD is still RC1 and the index contains no unrelated staged changes.
2. Run the verifier against the current tree. Confirm that `source.manifest.sha256` and `source.tar.gz.sha256` pass from this directory. Rebuild the archive into a separate directory and compare its bytes when needed.
3. Stage only `approved-paths.txt`, inspect the staged diff, commit, compare the committed file set with the manifest, and only then create the annotated tag. Do not use `git add .`; do not add research files, secrets, this metadata directory or the companion RC2 report to the source-tag commit.

Commands prepared for approval (not executed):

```sh
git diff --cached --quiet
.venv-ml/bin/python scripts/deploy/prepare-release.py verify-tree \
  --manifest docs/deployment/releases/2026.11.18-rc2/source.manifest
git add --pathspec-from-file=docs/deployment/releases/2026.11.18-rc2/approved-paths.txt
git diff --cached --check
git diff --cached --stat
# Review all staged content before the next command.
git commit -m 'Release 2026.11.18-rc2: verified account/report workflows and deployment safety'
# Verify the new committed file list and content match source.manifest before tagging.
git tag -a v2026.11.18-rc2 -F docs/deployment/releases/2026.11.18-rc2/tag-annotation.txt
# Record the approved commit, then build/verify a complete bundle. No push or deployment is implied.
```

After approval, record the actual commit separately and pass it, the approved tag and the fingerprint explicitly to `deploy-release.sh`. Do not silently change the archive, manifest, RC1 tag or an existing RC2 tag. A source change requires a newly verified candidate and fingerprint.
