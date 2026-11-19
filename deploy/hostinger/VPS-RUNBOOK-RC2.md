# RC2 private installation and acceptance

This runbook supersedes RC1 installation instructions. The source candidate is not yet a commit/tag and must not be installed until its exact fingerprint and approved commit are recorded. The final readiness report and release metadata are companion approval artifacts outside the source payload; do not add them to the source-tag commit. No real VPS has been verified.

1. Review `docs/deployment/ICLIMATE_RC2_FINAL_READINESS.md` and the companion release metadata. Approve the source path list, commit and annotated `v2026.11.18-rc2` tag. Generate a complete git bundle from that tag; verify all tracked files match the source manifest, with no extra committed files. Keep RC1 unchanged.
2. Owner authorizes a fresh Ubuntu 24.04 KVM VPS and data transfer. Follow `VPS-RUNBOOK-RC1.md` provisioning, database-account and HTTPS procedures with the corrected scripts. Provisioning refuses active services/configurations; do not use it to repair a live host. Set and test the operator's sudo password before retiring root SSH access.
3. Install `/etc/iclimate/iclimate.env` as root:www-data 640 in directory 750. Require production/debug=false, password lockdown=true, yield gate=withhold, `ICLIMATE_YIELD_ACCURACY_VERIFIED=false`, `DB_CONNECTION=mysql` (Laravel's `mysql` driver against MariaDB 10.11; the `mariadb` driver skips MySQL-guarded migration steps), one definition per key, HTTPS URL and secure cookies. `deploy-release.sh` refuses the install otherwise. Set all real credentials. Backend Reverb publishes privately to 127.0.0.1:8080/http; frontend uses the public hostname:443/https and the same public app key. Set an explicit public Vite app key before building; no `${...}` environment substitutions.
4. Copy the approved complete bundle and manifest. The installer requires all approval identifiers explicitly:

   ```sh
   TAG=v2026.11.18-rc2 EXPECTED_COMMIT=<approved-commit> \
   EXPECTED_FINGERPRINT=<approved-source-fingerprint> \
   BUNDLE=<absolute-bundle-path> MANIFEST=<absolute-manifest-path> \
   VERIFY_ONLY=1 bash deploy-release.sh
   ```

   After identity checks pass, repeat without `VERIFY_ONLY=1` for the approved installation. It does not migrate or start services. `npm ci` applies the documented glob-parser mitigation; the build fails if it is missing. Raw audit warnings remain visible. Do not start a public Vite server.
5. DBA restores a verified logical backup into a disposable MariaDB10.11 database first. Use `CHECK_RELEASE=rc2 scripts/deploy/two-role-migration-check.sh` snapshots/compare and rehearse all **seven** migrations (six RC1 plus `2026_11_18_000001_create_association_report_submissions`). The history migration is additive and does not invent old submission snapshots. Its rollback refuses to drop populated history. Never force a destructive rollback; retain the database and revert application code only through a compatible approved plan.
6. After authorization and a fresh protected backup, migrate production with the temporary migration account, restore runtime credentials, and verify historical counts/fingerprints. Keep runtime SQL privileges restricted. Do not grant ordinary MAO Staff administrative database or application permissions.
7. Cache config/routes/views and test PHP-FPM access to the env and writable storage/cache. Backups require www-data ownership and mode700. Install units and validate with `systemd-analyze verify`; start the authorized new services. Run health checks, a real queued notification, scheduler reminder, failed-job recovery and backup restore drill. Obtain actual SMTP reset-email delivery and operational recovery-code evidence.
8. Validate Nginx with `nginx -t`, retain the private HTTPS allowlist include, remove the distro default site, preserve the public ACME challenge path, issue/renew TLS, and confirm off-host private denial. Test browser WebSocket subscription/reconnect through TLS and secure-cookie sessions on the actual hostname.
9. Complete private Farmer/ordinary MAO/MAO Head/chairman acceptance, including pending/rejected denial, review races, report versions/private documents, downloads and mobile/tablet layouts. Keep yield estimates withheld and label scientific accuracy unverified.
10. Public launch requires explicit owner approval, trained staff recovery operations, verified backup monitoring and target-environment security checks. Missing scientific evidence is not permission to expose unvalidated yield estimates.
