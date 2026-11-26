#!/usr/bin/env bash
# Tendari — one-time VPS provisioning (Debian/Ubuntu + nginx + php-fpm).
#
# Run ON THE VPS as root, from a checkout of this repo:
#   sudo ./deploy/setup-vps.sh --domain tendari.org --app-dir /var/www/tendari \
#        --user deploy --php 8.3 --email you@example.com
#
# It installs packages, installs the nginx server block (deploy/nginx.conf with
# domain/path/PHP socket substituted), enables the site, reloads nginx, adds the
# cron jobs, and (optionally) obtains a Let's Encrypt certificate via certbot.
set -euo pipefail

DOMAIN=""
APP_DIR="/var/www/tendari"
DEPLOY_USER="deploy"
PHP_VERSION="8.3"
EMAIL=""
SKIP_PACKAGES=0

usage() { sed -n '2,10p' "$0"; exit 1; }
while [ $# -gt 0 ]; do
  case "$1" in
    --domain) DOMAIN="${2:-}"; shift 2 ;;
    --app-dir) APP_DIR="${2:-}"; shift 2 ;;
    --user) DEPLOY_USER="${2:-}"; shift 2 ;;
    --php) PHP_VERSION="${2:-}"; shift 2 ;;
    --email) EMAIL="${2:-}"; shift 2 ;;
    --skip-packages) SKIP_PACKAGES=1; shift ;;
    *) usage ;;
  esac
done
[ -n "$DOMAIN" ] || usage
[ "$(id -u)" -eq 0 ] || { echo "Run as root (sudo)."; exit 1; }

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
NGINX_SRC="$REPO_DIR/deploy/nginx.conf"
[ -f "$NGINX_SRC" ] || { echo "Missing $NGINX_SRC"; exit 1; }

echo "==> Domain: $DOMAIN | app: $APP_DIR | user: $DEPLOY_USER | PHP $PHP_VERSION"

if [ "$SKIP_PACKAGES" -eq 0 ]; then
  echo "==> Installing packages"
  apt-get update
  DEBIAN_FRONTEND=noninteractive apt-get install -y \
    nginx rsync git unzip curl \
    "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-sqlite3" \
    "php${PHP_VERSION}-curl" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" \
    certbot python3-certbot-nginx
fi

echo "==> Deploy user & directories"
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
  useradd -m -s /bin/bash "$DEPLOY_USER"
fi
usermod -aG www-data "$DEPLOY_USER"
mkdir -p "$APP_DIR" "$APP_DIR/storage/cache/ratelimit" "$APP_DIR/storage/logs" "$APP_DIR/storage/tenders"
chown -R "$DEPLOY_USER":www-data "$APP_DIR"
chmod -R ug+rwX "$APP_DIR/storage"
chmod 755 "$APP_DIR"

echo "==> PHP-FPM socket"
if [ ! -S "/run/php/php${PHP_VERSION}-fpm.sock" ]; then
  ALT_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)"
  if [ -n "$ALT_SOCK" ]; then
    DETECTED="$(basename "$ALT_SOCK" | sed -E 's/^php(.*)-fpm\.sock$/\1/')"
    echo "    php${PHP_VERSION} socket missing; detected PHP ${DETECTED} ($ALT_SOCK)"
    PHP_VERSION="$DETECTED"
  else
    echo "!! No php-fpm socket under /run/php — is PHP-FPM installed/running?"
  fi
fi

echo "==> Installing nginx server block"
SERVER_NAMES="$DOMAIN"
case "$DOMAIN" in www.*) ;; *) SERVER_NAMES="$DOMAIN www.$DOMAIN" ;; esac
CONF=/etc/nginx/sites-available/tendari.conf
if [ -f "$CONF" ] && grep -q 'ssl_certificate' "$CONF"; then
  echo "    Existing TLS-enabled config kept (delete $CONF to re-render)."
else
  sed -e "s#/var/www/tendari/public#${APP_DIR}/public#g" \
      -e "s#server_name tendari.org www.tendari.org;#server_name ${SERVER_NAMES};#g" \
      -e "s#php8.3-fpm.sock#php${PHP_VERSION}-fpm.sock#g" \
      "$NGINX_SRC" > "$CONF"
fi
ln -sf "$CONF" /etc/nginx/sites-enabled/tendari.conf
if ! grep -Rqs 'sites-enabled' /etc/nginx/nginx.conf; then
  echo "    nginx does not include sites-enabled/*; installing into conf.d/ instead"
  rm -f /etc/nginx/sites-enabled/tendari.conf
  cp "$CONF" /etc/nginx/conf.d/tendari.conf
fi
nginx -t
systemctl reload nginx || systemctl restart nginx
if nginx -T 2>/dev/null | grep -q "server_name .*${DOMAIN}"; then
  echo "    Loaded server block for $DOMAIN"
else
  echo "!! nginx does not report a server_name for $DOMAIN — check $( [ -f /etc/nginx/conf.d/tendari.conf ] && echo /etc/nginx/conf.d/tendari.conf || echo "$CONF")"
fi

echo "==> PHP-FPM environment (edit values!)"
POOL="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
if [ -f "$POOL" ] && ! grep -q '# BEGIN tendari env' "$POOL"; then
  {
    echo ""
    echo "# BEGIN tendari env"
    grep -E '^env\[' "$REPO_DIR/deploy/php-fpm.env.example"
    echo "# END tendari env"
  } >> "$POOL"
  echo "    Appended env[] block to $POOL — set real secrets, then: systemctl reload php${PHP_VERSION}-fpm"
fi
systemctl reload "php${PHP_VERSION}-fpm" || true

echo "==> Cron jobs"
cat > /etc/cron.d/tendari <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
*/5 * * * *   $DEPLOY_USER  cd $APP_DIR && flock -n /tmp/tendari-notify.lock   php cron/notify.php   >> storage/logs/cron.log 2>&1
*/15 * * * *  $DEPLOY_USER  cd $APP_DIR && flock -n /tmp/tendari-docs.lock     php cron/documents.php >> storage/logs/cron.log 2>&1
*/30 * * * *  $DEPLOY_USER  cd $APP_DIR && flock -n /tmp/tendari-fetch.lock    php cron/fetch.php    >> storage/logs/cron.log 2>&1
*/30 * * * *  $DEPLOY_USER  cd $APP_DIR && flock -n /tmp/tendari-health.lock   php cron/health.php   >> storage/logs/cron.log 2>&1
17 6 * * *    $DEPLOY_USER  cd $APP_DIR && flock -n /tmp/tendari-dedupe.lock   php cron/deduper.php  >> storage/logs/cron.log 2>&1
0 7 * * *     $DEPLOY_USER  cd $APP_DIR && flock -n /tmp/tendari-digest.lock   php cron/digest.php   >> storage/logs/cron.log 2>&1
EOF
chmod 644 /etc/cron.d/tendari

echo "==> Migrations (if code is already deployed)"
if [ -f "$APP_DIR/cron/migrate.php" ]; then
  sudo -u "$DEPLOY_USER" php "$APP_DIR/cron/migrate.php" || true
  chgrp -R www-data "$APP_DIR/storage" || true
  chmod -R ug+rwX "$APP_DIR/storage"
else
  echo "    Code not present yet — run the GitHub deploy, then: php $APP_DIR/cron/migrate.php"
fi

CERT_DOMAINS=(-d "$DOMAIN")
case "$DOMAIN" in
  www.*) CERT_DOMAINS+=(-d "${DOMAIN#www.}") ;;
  *)     CERT_DOMAINS+=(-d "www.$DOMAIN") ;;
esac
if [ -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
  echo "==> Certificate already exists — installing it into nginx"
  certbot install --cert-name "$DOMAIN" --non-interactive \
    || echo "!! certbot install failed; verify the server block is loaded (nginx -T | grep server_name) then: certbot install --cert-name $DOMAIN"
elif [ -n "$EMAIL" ]; then
  echo "==> Let's Encrypt certificate"
  certbot --nginx "${CERT_DOMAINS[@]}" --non-interactive --agree-tos -m "$EMAIL" --redirect \
    || echo "!! certbot failed (DNS/port 80?). Fix DNS then re-run: certbot --nginx ${CERT_DOMAINS[*]}"
else
  echo "==> TLS skipped. When DNS points here, run:"
  echo "    certbot --nginx ${CERT_DOMAINS[*]} --redirect"
fi

echo ""
echo "Done. Next:"
echo "  1. Edit $POOL (tendari env block) and reload php${PHP_VERSION}-fpm"
echo "  2. Set GitHub secret VPS_DEPLOY_PATH = $APP_DIR"
echo "  3. Push to main (or re-run the Deploy workflow)"
echo "  4. Test: curl -I https://$DOMAIN"
