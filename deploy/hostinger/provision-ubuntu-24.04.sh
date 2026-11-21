#!/usr/bin/env bash
# iClimate — provision a fresh Hostinger KVM VPS (Ubuntu 24.04 LTS) for the RC1 release. Run as root, once.
#
#   OPERATOR=deploy OPERATOR_PUBKEY="ssh-ed25519 AAAA… you@laptop" ADMIN_IP=203.0.113.10 \
#     bash provision-ubuntu-24.04.sh
#
# Installs only the platform; the application itself is deployed by deploy-release.sh. Nothing is opened to the
# public: install the private application vhost as described in the runbook before enabling the application.
#
# Version traps this script avoids (checked against the v2026.11.18-rc1 lockfiles):
#   - composer.lock needs PHP >= 8.4.1 (Symfony 8); Ubuntu 24.04 ships 8.3  -> PHP 8.4 from ppa:ondrej/php
#   - Vite 7 needs Node 20.19+ / 22.12+;   Ubuntu 24.04 ships Node 18        -> Node 22 from the NodeSource repository
#   - the model was pickled with scikit-learn 1.6.1                          -> pinned in python_scripts/requirements.txt
#   - Ubuntu 24.04 ships MariaDB 10.11 (migrations were rehearsed on 10.4)   -> rehearse on this server before migrating
set -Eeuo pipefail
: "${OPERATOR:?OPERATOR (non-root deploy user) required}"
: "${OPERATOR_PUBKEY:?OPERATOR_PUBKEY (SSH public key of the operator) required}"
: "${ADMIN_IP:?ADMIN_IP (the only address allowed to open the site before launch) required}"
[ "$(id -u)" = 0 ] || { echo "run as root"; exit 1; }
grep -qx 'ID=ubuntu' /etc/os-release && grep -qx 'VERSION_ID="24.04"' /etc/os-release || { echo "expected Ubuntu 24.04"; exit 1; }
APP=/var/www/iclimate_system
[[ "$OPERATOR" =~ ^[a-z][a-z0-9_-]{0,31}$ ]] && [ "$OPERATOR" != root ] || { echo "invalid non-root operator name"; exit 1; }
python3 - "$ADMIN_IP" <<'PY' || { echo "ADMIN_IP must be a single IP address"; exit 1; }
import ipaddress, sys
ipaddress.ip_address(sys.argv[1])
PY
[[ "$OPERATOR_PUBKEY" != *$'\n'* && "$OPERATOR_PUBKEY" != *$'\r'* ]] || { echo "expected one SSH public key"; exit 1; }
# This is a fresh-server installer, never a repair/restart tool for a live host.
for service in mariadb nginx php8.4-fpm; do
    if systemctl is-active --quiet "$service"; then echo "$service is already active; refusing fresh provisioning"; exit 1; fi
done
for path in "$APP" /etc/iclimate /etc/nginx/snippets/iclimate-private.conf /etc/mysql/mariadb.conf.d/60-iclimate.cnf /etc/ssh/sshd_config.d/60-iclimate.conf.pending /etc/ssh/sshd_config.d/60-iclimate.conf /etc/apt/sources.list.d/nodesource.list /etc/apt/keyrings/nodesource.gpg; do
    [ ! -e "$path" ] && [ ! -L "$path" ] || { echo "existing installation path: $path; refusing overwrite"; exit 1; }
done
[ -f "$(dirname "$0")/mariadb-iclimate.cnf" ] || { echo "mariadb-iclimate.cnf missing"; exit 1; }
work=$(mktemp -d)
trap 'rm -rf -- "$work"' EXIT
printf '%s\n' "$OPERATOR_PUBKEY" > "$work/operator.pub"
ssh-keygen -lf "$work/operator.pub" >/dev/null || { echo "invalid SSH public key"; exit 1; }
export DEBIAN_FRONTEND=noninteractive

step() { printf '\n== %s\n' "$1"; }

step "System updates and automatic security updates"
apt-get update -q && apt-get -y -q upgrade
apt-get -y -q install ca-certificates curl gnupg software-properties-common unattended-upgrades ufw fail2ban git unzip acl openssl rsync
dpkg-reconfigure -f noninteractive unattended-upgrades

step "Operator account with SSH key (root and password logins are disabled only after you confirm the key works)"
id "$OPERATOR" >/dev/null 2>&1 || adduser --disabled-password --gecos "" "$OPERATOR"
usermod -aG sudo,www-data "$OPERATOR"
echo "   Before ending the root session, set the operator's local sudo password with: passwd $OPERATOR"
install -d -m 700 -o "$OPERATOR" -g "$OPERATOR" "/home/$OPERATOR/.ssh"
grep -qxF "$OPERATOR_PUBKEY" "/home/$OPERATOR/.ssh/authorized_keys" 2>/dev/null || echo "$OPERATOR_PUBKEY" >> "/home/$OPERATOR/.ssh/authorized_keys"
chown "$OPERATOR:$OPERATOR" "/home/$OPERATOR/.ssh/authorized_keys"; chmod 600 "/home/$OPERATOR/.ssh/authorized_keys"
cat > /etc/ssh/sshd_config.d/60-iclimate.conf.pending <<'EOF'
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
EOF
echo "   Written /etc/ssh/sshd_config.d/60-iclimate.conf.pending. From a SECOND terminal, log in as $OPERATOR with the key,"
echo "   then: sudo mv /etc/ssh/sshd_config.d/60-iclimate.conf.pending /etc/ssh/sshd_config.d/60-iclimate.conf && sudo sshd -t && sudo systemctl reload ssh"

step "Firewall: SSH, HTTP, HTTPS only (MariaDB 3306, Reverb 8080 and the ML service 8010 stay closed)"
ufw default deny incoming; ufw default allow outgoing
ufw allow OpenSSH; ufw allow 80/tcp; ufw allow 443/tcp
ufw --force enable
systemctl enable --now fail2ban

step "PHP 8.4 (ppa:ondrej/php) with the extensions RC1 needs"
add-apt-repository -y ppa:ondrej/php && apt-get update -q
apt-get -y -q install php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
    php8.4-gd php8.4-intl php8.4-bcmath php8.4-opcache php8.4-sqlite3
# The distribution's "composer" package depends on the generic php-cli and can pull in another PHP version: use the
# official installer, checked against its published signature, and keep "php" pointing at 8.4.
update-alternatives --set php /usr/bin/php8.4
php -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' || { echo "PHP 8.4.1+ not active"; exit 1; }
if ! command -v composer >/dev/null; then
    curl -fsSL -o "$work/composer-setup.php" https://getcomposer.org/installer
    expected=$(curl -fsSL https://composer.github.io/installer.sig)
    actual=$(sha384sum "$work/composer-setup.php" | cut -d' ' -f1)
    [ "$expected" = "$actual" ] || { echo "Composer installer signature mismatch"; exit 1; }
    php "$work/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
fi
sed -i 's/^;\?expose_php = .*/expose_php = Off/' /etc/php/8.4/fpm/php.ini
systemctl enable --now php8.4-fpm

step "Node 22 LTS (NodeSource signed repository) — only to build the frontend assets"
install -d -m 755 /etc/apt/keyrings
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor --yes -o /etc/apt/keyrings/nodesource.gpg
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" > /etc/apt/sources.list.d/nodesource.list
apt-get update -q && apt-get -y -q install nodejs
node -e 'const [a,b]=process.versions.node.split(".").map(Number); process.exit(a>22||(a===22&&b>=12)?0:1)' || { echo "Node 22.12+ not active"; exit 1; }

step "Python 3.12 for the ML service"
apt-get -y -q install python3 python3-venv python3-dev build-essential

step "MariaDB (localhost only)"
apt-get -y -q install mariadb-server mariadb-client
[ -f "$(dirname "$0")/mariadb-iclimate.cnf" ] && install -m 644 "$(dirname "$0")/mariadb-iclimate.cnf" /etc/mysql/mariadb.conf.d/60-iclimate.cnf
[ -f "$(dirname "$0")/iclimate-logrotate" ] && install -m 644 "$(dirname "$0")/iclimate-logrotate" /etc/logrotate.d/iclimate
systemctl enable mariadb && systemctl restart mariadb
ss -ltn | grep -q '127.0.0.1:3306' && ! ss -ltn | grep -qE '(0\.0\.0\.0|\*|\[::\]):3306' || { echo "MariaDB is not bound to localhost only"; exit 1; }
echo "   Next (DBA, interactive): mariadb-secure-installation, then the accounts in mariadb-migration-account.sql / phase-18-dba-handoff.md"

step "Nginx, Certbot, private access"
apt-get -y -q install nginx certbot python3-certbot-nginx
install -d -m 755 /etc/nginx/snippets
printf '# Private until launch: only the operator may open the site. Remove this file (and reload Nginx) to open it.\nallow %s;\ndeny all;\n' "$ADMIN_IP" > /etc/nginx/snippets/iclimate-private.conf
echo "   Put 'include snippets/iclimate-private.conf;' in the 443 server block only (port 80 must keep the ACME challenge open): VPS-RUNBOOK-RC1.md step 7."
systemctl enable --now nginx

step "Application folders and secrets location"
install -d -m 2750 -o "$OPERATOR" -g www-data "$APP"
install -d -m 750 -o root -g www-data /etc/iclimate
[ -f /etc/iclimate/iclimate.env ] || { install -m 640 -o root -g www-data /dev/null /etc/iclimate/iclimate.env; echo "   Fill /etc/iclimate/iclimate.env from iclimate.env.example (keep ICLIMATE_PASSWORD_LOCKDOWN=true, ICLIMATE_YIELD_GATE=withhold)"; }
install -d -m 700 -o www-data -g www-data /var/backups/iclimate

step "Done — versions"
php -v | head -1; node -v; python3 -V; mariadb --version; nginx -v 2>&1; ufw status | head -5
