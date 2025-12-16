#!/usr/bin/env bash
set -euo pipefail

# =========================
# Configurable variables
# =========================
DOMAIN="speedtest.sarkernet.com.bd"
EXAMPLE_DOMAIN="example.com"
EMAIL="admin@sarkernet.com.bd"
WEBROOT="/var/www/html/speedtest"
APACHE_USER="www-data"
APACHE_GROUP="www-data"
USE_STAGING="false"   # true = staging certs, false = production

# =========================
# Helper functions
# =========================
log() { echo -e "\e[32m[+] $*\e[0m"; }
warn() { echo -e "\e[33m[!] $*\e[0m"; }
err() { echo -e "\e[31m[!] $*\e[0m" >&2; }

require_root() {
  if [[ "${EUID}" -ne 0 ]]; then
    err "Run as root (use: sudo $0)"
    exit 1
  fi
}

detect_apt() {
  if ! command -v apt-get >/dev/null 2>&1; then
    err "This script supports Debian/Ubuntu only."
    exit 1
  fi
}

# =========================
# System setup
# =========================
update_system() {
  log "Updating system packages..."
  apt-get update -y && apt-get upgrade -y
}

install_packages() {
  log "Installing Apache, PHP, Certbot, and required packages..."
  DEBIAN_FRONTEND=noninteractive apt-get install -y \
    apache2 php libapache2-mod-php unzip git \
    openssl certbot python3-certbot-apache curl dnsutils
}

configure_firewall() {
  if command -v ufw >/dev/null 2>&1; then
    log "Configuring UFW to allow HTTP/HTTPS..."
    ufw allow 80/tcp || true
    ufw allow 443/tcp || true
  else
    warn "UFW not found; ensure ports 80 and 443 are open."
  fi
}

enable_apache_modules() {
  log "Enabling Apache modules..."
  a2enmod ssl headers rewrite || true
}

# =========================
# Deploy LibreSpeed
# =========================
deploy_librespeed() {
  log "Downloading LibreSpeed..."
  cd /tmp
  rm -rf speedtest || true
  git clone https://github.com/librespeed/speedtest.git

  log "Deploying LibreSpeed files to ${WEBROOT}..."
  mkdir -p "${WEBROOT}"
  cp speedtest/index.html speedtest/speedtest.js speedtest/speedtest_worker.js speedtest/favicon.ico "${WEBROOT}/"
  cp -r speedtest/backend "${WEBROOT}/"
  cp -r speedtest/results "${WEBROOT}/"

  log "Setting permissions..."
  chown -R "${APACHE_USER}:${APACHE_GROUP}" "${WEBROOT}"
  chmod -R 755 "${WEBROOT}"
}

# =========================
# Apache VirtualHost + SSL
# =========================
create_http_vhost() {
  for dom in "${DOMAIN}" "${EXAMPLE_DOMAIN}"; do
    local vhost="/etc/apache2/sites-available/${dom}.conf"
    log "Creating Apache HTTP vhost for ${dom}..."
    cat > "${vhost}" <<EOF
<VirtualHost *:80>
    ServerName ${dom}
    ServerAdmin ${EMAIL}
    DocumentRoot ${WEBROOT}

    <Directory ${WEBROOT}>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    Alias /.well-known/acme-challenge/ ${WEBROOT}/.well-known/acme-challenge/
    <Directory ${WEBROOT}/.well-known/acme-challenge/>
        Options None
        AllowOverride None
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${dom}-error.log
    CustomLog \${APACHE_LOG_DIR}/${dom}-access.log combined
</VirtualHost>
EOF

    a2ensite "${dom}.conf" || true
  done
}

reload_apache() {
  log "Testing and reloading Apache..."
  apache2ctl -t
  systemctl reload apache2
  systemctl enable apache2
}

obtain_certificate() {
  local extra_flags=()
  if [[ "${USE_STAGING}" == "true" ]]; then
    warn "Using Let's Encrypt STAGING environment."
    extra_flags+=(--test-cert)
  fi

  for dom in "${DOMAIN}" "${EXAMPLE_DOMAIN}"; do
    log "Requesting Let's Encrypt certificate for ${dom}..."
    certbot --apache \
      -d "${dom}" \
      --email "${EMAIL}" \
      --agree-tos \
      --redirect \
      --no-eff-email \
      "${extra_flags[@]}"
  done
}

harden_ssl_headers() {
  for dom in "${DOMAIN}" "${EXAMPLE_DOMAIN}"; do
    local ssl_vhost="/etc/apache2/sites-available/${dom}-le-ssl.conf"
    if [[ -f "${ssl_vhost}" ]]; then
      log "Adding security headers to ${ssl_vhost}..."
      if ! grep -q "Strict-Transport-Security" "${ssl_vhost}"; then
        sed -i '/<\/VirtualHost>/i \
  # Security headers\n\
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"\n\
  Header always set X-Content-Type-Options "nosniff"\n\
  Header always set X-Frame-Options "SAMEORIGIN"\n\
  Header always set Referrer-Policy "no-referrer-when-downgrade"\n' "${ssl_vhost}"
      fi
    fi
  done
}

# =========================
# Post checks
# =========================
post_checks() {
  for dom in "${DOMAIN}" "${EXAMPLE_DOMAIN}"; do
    log "Verifying HTTP/HTTPS for ${dom}..."
    curl -I "http://${dom}" || true
    curl -I "https://${dom}" || true

    log "Listing certificate files for ${dom}..."
    ls -l "/etc/letsencrypt/live/${dom}" || true
  done

  log "Testing renewal dry-run..."
  certbot renew --dry-run || warn "Renewal dry-run failed."
}

# =========================
# Main
# =========================
main() {
  require_root
  detect_apt
  update_system
  install_packages
  configure_firewall
  enable_apache_modules
  deploy_librespeed
  create_http_vhost
  reload_apache
  obtain_certificate
  harden_ssl_headers
  reload_apache
  post_checks

  log "Setup complete!"
  warn "Access LibreSpeed at: https://${DOMAIN}/ or https://${EXAMPLE_DOMAIN}/"
  warn "Auto-renew handled by systemd timers. Check with: systemctl list-timers | grep certbot"
}

main "$@"
