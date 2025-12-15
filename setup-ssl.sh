#!/usr/bin/env bash
set -euo pipefail

# =========================
# Configurable variables
# =========================
DOMAIN="speedtest.sarkernet.com.bd"
EMAIL="admin@sarkernet.com.bd"   # Used for Let's Encrypt registration and renewal notices
WEBROOT="/var/www/speedtest"
APACHE_USER="www-data"
APACHE_GROUP="www-data"
USE_STAGING="false"              # true = use Let's Encrypt staging (no rate limits, not trusted), false = production

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
  if command -v apt-get >/dev/null 2>&1; then
    return 0
  else
    err "This script currently supports Debian/Ubuntu (apt)."
    exit 1
  fi
}

check_dns() {
  log "Checking DNS A record for ${DOMAIN}..."
  if ! command -v dig >/dev/null 2>&1; then apt-get update -y && apt-get install -y dnsutils; fi
  SERVER_IP="$(curl -s https://api.ipify.org || true)"
  DNS_IP="$(dig +short A "${DOMAIN}" | tail -n1 || true)"
  warn "Server public IP: ${SERVER_IP:-unknown}"
  warn "DNS A record IP:  ${DNS_IP:-unknown}"
  if [[ -n "${SERVER_IP}" && -n "${DNS_IP}" && "${SERVER_IP}" != "${DNS_IP}" ]]; then
    warn "DNS A record does not point to this server. Let's Encrypt will fail. Fix DNS before continuing."
    read -r -p "Continue anyway? (y/N): " ans
    [[ "${ans:-N}" =~ ^[Yy]$ ]] || exit 1
  fi
}

ensure_packages() {
  log "Installing required packages..."
  apt-get update -y
  DEBIAN_FRONTEND=noninteractive apt-get install -y \
    apache2 \
    openssl \
    certbot \
    python3-certbot-apache \
    curl
}

configure_firewall() {
  if command -v ufw >/dev/null 2>&1; then
    log "Configuring UFW to allow HTTP/HTTPS..."
    ufw allow 80/tcp || true
    ufw allow 443/tcp || true
  else
    warn "UFW not found; ensure ports 80 and 443 are open in your firewall."
  fi
}

enable_apache_modules() {
  log "Enabling Apache modules (ssl, headers, rewrite)..."
  a2enmod ssl || true
  a2enmod headers || true
  a2enmod rewrite || true
}

create_webroot() {
  log "Preparing webroot at ${WEBROOT}..."
  mkdir -p "${WEBROOT}"
  chown -R "${APACHE_USER}:${APACHE_GROUP}" "${WEBROOT}"
  if [[ ! -f "${WEBROOT}/index.html" ]]; then
    cat > "${WEBROOT}/index.html" <<EOF
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>${DOMAIN}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:2rem}code{background:#f5f5f5;padding:.2rem .4rem;border-radius:.2rem}</style>
</head>
<body>
  <h1>${DOMAIN}</h1>
  <p>Apache is running. SSL will be installed after Certbot completes.</p>
</body>
</html>
EOF
  fi
}

create_http_vhost() {
  local vhost="/etc/apache2/sites-available/${DOMAIN}.conf"
  log "Creating HTTP VirtualHost at ${vhost}..."
  cat > "${vhost}" <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ServerAdmin ${EMAIL}
    DocumentRoot ${WEBROOT}

    <Directory ${WEBROOT}>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Allow Let's Encrypt HTTP-01 challenge
    Alias /.well-known/acme-challenge/ ${WEBROOT}/.well-known/acme-challenge/
    <Directory ${WEBROOT}/.well-known/acme-challenge/>
        Options None
        AllowOverride None
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${DOMAIN}-error.log
    CustomLog \${APACHE_LOG_DIR}/${DOMAIN}-access.log combined
</VirtualHost>
EOF

  a2ensite "${DOMAIN}.conf" || true
}

apache_test_reload() {
  log "Testing Apache config..."
  apache2ctl -t
  log "Reloading Apache..."
  systemctl reload apache2
  systemctl enable apache2
  systemctl status apache2 --no-pager || true
}

obtain_certificate() {
  local extra_flags=()
  if [[ "${USE_STAGING}" == "true" ]]; then
    warn "Using Let's Encrypt STAGING environment (test certs, not trusted)."
    extra_flags+=(--test-cert)
  fi

  log "Requesting Let's Encrypt certificate for ${DOMAIN} via Apache plugin..."
  certbot --apache \
    -d "${DOMAIN}" \
    --email "${EMAIL}" \
    --agree-tos \
    --redirect \
    --no-eff-email \
    "${extra_flags[@]}"
}

harden_ssl_headers() {
  local ssl_vhost="/etc/apache2/sites-available/${DOMAIN}-le-ssl.conf"
  if [[ -f "${ssl_vhost}" ]]; then
    log "Adding basic security headers to ${ssl_vhost}..."
    # Ensure Headers module is active
    a2enmod headers || true

    # Only append if not already present
    if ! grep -q "Header always set Strict-Transport-Security" "${ssl_vhost}"; then
      sed -i '/<\/VirtualHost>/i \
  # Security headers\n\
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"\n\
  Header always set X-Content-Type-Options "nosniff"\n\
  Header always set X-Frame-Options "SAMEORIGIN"\n\
  Header always set Referrer-Policy "no-referrer-when-downgrade"\n' "${ssl_vhost}"
    fi
  else
    warn "SSL vhost ${ssl_vhost} not found; Certbot may have created a different file. Skipping header hardening."
  fi
}

post_checks() {
  log "Verifying HTTP and HTTPS reachability..."
  sleep 2
  warn "HTTP check:"
  curl -I "http://${DOMAIN}" || true
  warn "HTTPS check:"
  curl -I "https://${DOMAIN}" || true

  log "Listing certificate files:"
  ls -l "/etc/letsencrypt/live/${DOMAIN}" || true

  log "Testing renewal dry-run..."
  certbot renew --dry-run || warn "Renewal dry-run failed; check logs at /var/log/letsencrypt/letsencrypt.log"
}

main() {
  require_root
  detect_apt
  check_dns
  ensure_packages
  configure_firewall
  enable_apache_modules
  create_webroot
  create_http_vhost
  apache_test_reload
  obtain_certificate
  harden_ssl_headers
  apache_test_reload
  post_checks

  log "SSL setup completed for ${DOMAIN}."
  warn "If you plan to deploy a speedtest UI (e.g., LibreSpeed), place files under ${WEBROOT}."
  warn "Auto-renew is handled by systemd timers. Verify with: systemctl list-timers | grep certbot"
}

main "$@"
