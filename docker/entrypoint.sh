#!/bin/bash

set -e
set -x

is_alpine() {
  [ -f /etc/alpine-release ]
}

# Cleanup
rm -rf /var/www/html/*

# Copy frontend files
cp /speedtest/*.js /var/www/html/

# Copy favicon
cp /speedtest/favicon.ico /var/www/html/

# Set custom webroot on alpine
if is_alpine; then
  echo "ALPINE IMAGE"
  sed -i "s#\"/var/www/localhost/htdocs\"#\"/var/www/html\"#g" /etc/apache2/httpd.conf
else
  echo "DEBIAN IMAGE"
fi


# Set up backend side for standlone modes
if [[ "$MODE" == "standalone" || "$MODE" == "dual" ]]; then
  cp -r /speedtest/backend/ /var/www/html/backend
  if [ ! -z "$IPINFO_APIKEY" ]; then
    sed -i s/\$IPINFO_APIKEY\ =\ \'\'/\$IPINFO_APIKEY\ =\ \'$IPINFO_APIKEY\'/g /var/www/html/backend/getIP_ipInfo_apikey.php
  fi
fi

if [ "$MODE" == "backend" ]; then
  cp -r /speedtest/backend/* /var/www/html
  if [ ! -z "$IPINFO_APIKEY" ]; then
    sed -i s/\$IPINFO_APIKEY\ =\ \'\'/\$IPINFO_APIKEY\ =\ \'$IPINFO_APIKEY\'/g /var/www/html/getIP_ipInfo_apikey.php
  fi
fi

# Set up index.php for frontend-only or standalone modes
if [[ "$MODE" == "frontend" || "$MODE" == "dual" ]]; then
  cp -av /speedtest/frontend/* /var/www/html/
elif [ "$MODE" == "standalone" ]; then
  cp -av /speedtest/frontend/* /var/www/html/
  echo '[{"name":"local","server":"/backend",  "dlURL": "garbage.php", "ulURL": "empty.php", "pingURL": "empty.php", "getIpURL": "getIP.php", "sponsorName": "", "sponsorURL": "", "id":1 }]' > /var/www/html/server-list.json
fi

# Apply Telemetry settings when running in standalone or frontend mode and telemetry is enabled
if [[ "$TELEMETRY" == "true" && ("$MODE" == "frontend" || "$MODE" == "standalone" || "$MODE" == "dual") ]]; then
  cp -r /speedtest/results /var/www/html/results

  if [ "$MODE" == "frontend" ]; then
    mkdir /var/www/html/backend
    cp /speedtest/backend/getIP_util.php /var/www/html/backend
  fi

  if [ "$DB_TYPE" == "mysql" ]; then
    sed -i 's/$db_type = '\''.*'\''/$db_type = '\'$DB_TYPE\''/g' /var/www/html/results/telemetry_settings.php
    sed -i 's/$MySql_username = '\''.*'\''/$MySql_username = '\'$DB_USERNAME\''/g' /var/www/html/results/telemetry_settings.php
    sed -i 's/$MySql_password = '\''.*'\''/$MySql_password = '\'$DB_PASSWORD\''/g' /var/www/html/results/telemetry_settings.php
    sed -i 's/$MySql_hostname = '\''.*'\''/$MySql_hostname = '\'$DB_HOSTNAME\''/g' /var/www/html/results/telemetry_settings.php
    sed -i 's/$MySql_databasename = '\''.*'\''/$MySql_databasename = '\'$DB_NAME\''/g' /var/www/html/results/telemetry_settings.php
    if [ "$DB_PORT" != "" ]; then
      sed -i 's/$MySql_port = '\''.*'\''/$MySql_port = '\'$DB_PORT\''/g' /var/www/html/results/telemetry_settings.php
    fi
  elif [ "$DB_TYPE" == "postgresql" ]; then
    sed -i 's/$db_type = '\''.*'\''/$db_type = '\'$DB_TYPE\''/g' /var/www/html/results/telemetry_settings.php
    sed -i 's/$PostgreSql_username = '\''.*'\''/$PostgreSql_username = '\'$DB_USERNAME\''/g' /var/www/html/results/telemetry_settings.php
    sed -i 's/$PostgreSql_password = '\''.*'\''/$PostgreSql_password = '\'$DB_PASSWORD\''/g' /var/www/html/results/telemetry_settings.php
    sed -i 's/$PostgreSql_hostname = '\''.*'\''/$PostgreSql_hostname = '\'$DB_HOSTNAME\''/g' /var/www/html/results/telemetry_settings.php
    sed -i 's/$PostgreSql_databasename = '\''.*'\''/$PostgreSql_databasename = '\'$DB_NAME\''/g' /var/www/html/results/telemetry_settings.php
  else
    sed -i s/\$db_type\ =\ \'.*\'/\$db_type\ =\ \'sqlite\'\/g /var/www/html/results/telemetry_settings.php
  fi

  sed -i s/\$Sqlite_db_file\ =\ \'.*\'/\$Sqlite_db_file=\'\\\/database\\\/db.sql\'/g /var/www/html/results/telemetry_settings.php
  sed -i s/\$stats_password\ =\ \'.*\'/\$stats_password\ =\ \'$PASSWORD\'/g /var/www/html/results/telemetry_settings.php

  if [ "$ENABLE_ID_OBFUSCATION" == "true" ]; then
    sed -i s/\$enable_id_obfuscation\ =\ .*\;/\$enable_id_obfuscation\ =\ true\;/g /var/www/html/results/telemetry_settings.php
  fi

  if [ "$REDACT_IP_ADDRESSES" == "true" ]; then
    sed -i s/\$redact_ip_addresses\ =\ .*\;/\$redact_ip_addresses\ =\ true\;/g /var/www/html/results/telemetry_settings.php
  fi

  mkdir -p /database/
  if is_alpine; then
    chown -R apache /database/
  else
    chown -R www-data /database/
  fi
fi

if is_alpine; then
  chown -R apache /var/www/html/*
else
  chown -R www-data /var/www/html/*
fi

# Allow selection of Apache port for network_mode: host
if [ "$WEBPORT" != "80" ]; then
  if is_alpine; then
    sed -i "s/^Listen 80\$/Listen $WEBPORT/g" /etc/apache2/httpd.conf
  else
    sed -i "s/^Listen 80\$/Listen $WEBPORT/g" /etc/apache2/ports.conf
    sed -i "s/*:80>/*:$WEBPORT>/g" /etc/apache2/sites-available/000-default.conf
  fi
fi

echo "Done, Starting APACHE"

# This runs apache
if is_alpine; then
  exec httpd -DFOREGROUND
else
  exec apache2-foreground
fi
