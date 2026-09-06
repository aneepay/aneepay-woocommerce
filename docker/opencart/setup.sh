#!/bin/sh
# AneePay OpenCart test shop provisioning.
# Runs inside the `setup` container (php:7.4-apache) as root.
set -e

echo "Waiting for the OpenCart DB ..."
until php -r '$m=@mysqli_connect(getenv("DB_HOST"),getenv("DB_USER"),getenv("DB_PASS"),"",(int)getenv("DB_PORT")); if($m){echo "ok\n"; exit(0);} echo "."; exit(1);' >/dev/null 2>&1; do
	sleep 2
done
echo "DB is up."

echo "Installing OpenCart 3.0.3.8 (headless) ..."
cd /var/www/html/install
php cli_install.php install \
	--db_hostname "${DB_HOST:-db}" \
	--db_port "${DB_PORT:-3306}" \
	--db_username "${DB_USER:-oc}" \
	--db_password "${DB_PASS:-oc}" \
	--db_database "${DB_NAME:-opencart}" \
	--db_driver mysqli \
	--username admin \
	--password admin \
	--email admin@example.com \
	--http_server "${HTTP_SERVER:-http://localhost:8082/}"

echo "Installing AneePay extension files ..."
cp -a /plugin/admin/.   /var/www/html/admin/
cp -a /plugin/catalog/. /var/www/html/catalog/
cp -a /plugin/system/.  /var/www/html/system/

echo "Enabling the AneePay payment method + creating tables ..."
php /opt/enable.php

echo "Removing the installer ..."
rm -rf /var/www/html/install

chmod o+w -R /var/www/html/system/storage /var/www/html/image 2>/dev/null || true

echo "OpenCart ready: http://localhost:8082  (admin / admin)"
