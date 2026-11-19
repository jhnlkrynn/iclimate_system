# VPS runbook — iClimate v2026.11.18-rc1 (private deployment)

> RC2 work supersedes these RC1 installation identifiers. Use `VPS-RUNBOOK-RC2.md` and explicit approved release arguments; do not install RC1 unchanged.

**For:** the project owner (purchase, DNS, approvals), the operator (server), the DBA (database). Complements
`docs/deployment/ICLIMATE_RELEASE_HANDOFF.md` (release, approval gates, recovery rollout).

The site stays private (only `ADMIN_IP`) until step 9. Nothing here migrates the active Mac database.

## 0. Purchase (owner)

Hostinger **KVM VPS** (not shared or cloud web hosting — Reverb, the queue worker and the ML service are long-running):

| Item | Value |
|---|---|
| OS | Ubuntu 24.04 LTS (clean image, no control panel) |
| CPU / RAM | 2+ vCPU, **8 GB** (4 GB minimum) |
| Disk | 60 GB+ (database, uploads, daily backups) |
| Network | public IPv4; root access by SSH key |
| Region | nearest to Batangas (Asia) |

Give the operator: the VPS IPv4, root SSH access by key, and the owner's office IP for `ADMIN_IP`.
Do not change DNS yet.

## 1. Provision (operator, as root)

Copy `deploy/hostinger/` to the server, then:

```sh
OPERATOR=deploy OPERATOR_PUBKEY="ssh-ed25519 AAAA… operator" ADMIN_IP=<owner office IP> bash provision-ubuntu-24.04.sh
```

It installs PHP **8.4** (ppa:ondrej/php — `composer.lock` needs ≥ 8.4.1; Ubuntu ships 8.3), Composer (signature-checked),
Node **22** (Vite 7 needs ≥ 22.12; Ubuntu ships 18), Python 3.12, MariaDB (localhost only), Nginx, Certbot, the UFW
firewall (22/80/443) and fail2ban. Then, from a **second** terminal, confirm the key login as `deploy` and activate the
prepared SSH hardening (no root login, no passwords) as the script prints. Before ending the root session,
run `passwd deploy` to set a strong local sudo password and verify `sudo -v` from the second terminal;
`adduser --disabled-password` alone cannot authenticate sudo. Keep the app root owned by `deploy:www-data`,
mode `2750`; only `storage` and `bootstrap/cache` receive web-user write ACLs.

## 2. Database server (DBA)

`sudo mariadb-secure-installation` (root password, no anonymous users, no remote root). Create `iclimate_app`
(SELECT, INSERT, UPDATE, DELETE) and the temporary `iclimate_migrator` from `mariadb-migration-account.sql`,
both at `'127.0.0.1'` (`phase-18-dba-handoff.md`). Create the empty database `iclimate`.

## 3. Release (operator)

From the Mac, copy the release to the server (SSH encrypts the transfer):

```sh
scp storage/app/backups/preflight-2026-11-18/iclimate-v2026.11.18-rc1.bundle \
    storage/app/backups/preflight-2026-11-18/release-2026.11.18-rc1.manifest deploy@<VPS IP>:~
```

On the server: fill `/etc/iclimate/iclimate.env` from `iclimate.env.example` (`APP_ENV=production`,
`APP_DEBUG=false`, `ICLIMATE_PASSWORD_LOCKDOWN=true`, `ICLIMATE_YIELD_GATE=withhold`, `SESSION_SECURE_COOKIE=true`,
`APP_KEY`, `DB_*` = the app account, mail and API keys, Reverb keys), then:

```sh
BUNDLE=~/iclimate-v2026.11.18-rc1.bundle MANIFEST=~/release-2026.11.18-rc1.manifest bash deploy-release.sh
```

It refuses to continue unless the manifest fingerprint is `bd8f2c94…1266f`, the tag is commit `d43cd1aa…1170`, every
one of the 1,462 files matches, the model checksum is `f3a54e12…b777c`, and the production settings above are set. It
then installs dependencies from the release's lockfiles only (and scikit-learn 1.6.1).

## 4. Database copy — Option A (owner approval: transfer of personal and agricultural records)

On the Mac, when approved:

```sh
php artisan iclimate:backup-database --no-prune
B=$(ls -t storage/app/backups/*.backup | head -1)
(cd "$(dirname "$B")" && shasum -a 256 "$(basename "$B")" > "$(basename "$B").sha256")
gpg --symmetric --cipher-algo AES256 --output "$B.gpg" "$B"      # passphrase shared out of band, never in chat or email
ssh deploy@<VPS-IP> 'umask 077; mkdir -p ~/iclimate-incoming'
scp "$B.gpg" "$B.sha256" deploy@<VPS-IP>:~/iclimate-incoming/
```

On the server, use `umask 077`, decrypt into the incoming directory using the original backup basename,
and run `sha256sum -c <backup-basename>.sha256` from that directory. Restore into a **disposable** database first
(`restore-isolated-mariadb.sh`, which runs `mysqlcheck`), compare row counts, then load it into `iclimate`. Delete the
decrypted incoming file and the Mac's `.gpg` copy afterwards. Keep protected backups in
`/var/backups/iclimate` owned by `www-data:www-data`, mode `700`, so scheduled Laravel backups can write there;
install transferred files with `sudo install -o www-data -g www-data -m 600`. The 110 seeded rice-production rows stay labelled as seeded.

## 5. Migrations and MAO Head (owner approval)

Ubuntu 24.04 runs MariaDB **10.11**; local verification rehearsed the six migrations and restoration on 10.11.19 as well as the prior 10.4 evidence.
The actual Ubuntu server remains unverified. First rehearse here on the disposable
copy (`scripts/deploy/two-role-migration-check.sh` with `CHECK_DATABASE=<disposable>`), then follow
`phase-18-deployment-runbook.md` parts A–B on `iclimate` as `iclimate_migrator`. Then
`php artisan iclimate:assign-mao-head <approved email>` and `--apply`.
`php artisan config:cache && php artisan route:cache && php artisan view:cache`.

## 6. Services (operator)

Install the units from `deploy/hostinger/` into `/etc/systemd/system`, then
`systemctl enable --now iclimate-python iclimate-reverb iclimate-queue iclimate-scheduler.timer`.

## 7. HTTPS while still private (owner: DNS)

1. Owner points the A record of `iclimate-lian.tech` (and `www`) at the VPS IPv4 in Hostinger DNS (replacing the
   parking record). Wait until `dig +short iclimate-lian.tech` returns the VPS address.
2. Before any certificate exists, enable only a port-80 server that answers the Let's Encrypt challenge for everyone
   and nothing else (the private rule must not block it):

   ```nginx
   server {
       listen 80; listen [::]:80;
       server_name iclimate-lian.tech www.iclimate-lian.tech;
       location ^~ /.well-known/acme-challenge/ { allow all; root /var/www/html; }
       location / { return 404; }
   }
   ```
3. `sudo certbot certonly --webroot -w /var/www/html -d iclimate-lian.tech -d www.iclimate-lian.tech`
4. Now enable `nginx-iclimate.conf` (it refers to the new certificate), keep the same ACME location in its port-80
   block, and retain its `include snippets/iclimate-private.conf;` in the **443** server block.
   Confirm the installed file matches this private template; remove the distro default site, and test an unapproved IP receives 403. `sudo nginx -t && sudo systemctl reload nginx`.
   Renewals keep working through the open challenge location.

## 8. Verification (operator, from ADMIN_IP)

`php artisan iclimate:health` (exit 0) · `scripts/deploy/ml-activation-check.sh f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`
(exit 0) · 0 pending migrations · handoff §10 (Farmer, MAO, MAO Head and shared-password test sign-ins; Account
Requests; association reporting; real-time update; backup restore into a disposable database).

## 9. Open to the public (owner approval)

Only after: MAO Staff trained on recovery codes, the mobile release (or approved website-only recovery), step 8 green.
Remove `/etc/nginx/snippets/iclimate-private.conf`'s rules (or the include), `sudo nginx -t && sudo systemctl reload nginx`.
Then the DBA drops `iclimate_migrator`.
