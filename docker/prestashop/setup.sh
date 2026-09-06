#!/bin/sh
# AneePay PrestaShop test shop provisioning.
# The shop itself is auto-installed by the prestashop image entrypoint; this
# script just waits for it and installs + enables the AneePay module.
set -e

echo "Waiting for the PrestaShop auto-install ..."
for i in $(seq 1 120); do
	if php -r '$m=@mysqli_connect("db","ps","ps","prestashop"); $r=@mysqli_query($m,"SHOW TABLES LIKE \"ps_lang\""); exit($r && mysqli_num_rows($r) ? 0 : 1);' 2>/dev/null; then
		break
	fi
	sleep 2
done

# Ensure the writable dirs and the bind-mounted module are accessible.
chown -R www-data:www-data /var/www/html 2>/dev/null || true
chmod -R a+rwX /var/www/html/var /var/www/html/app /var/www/html/config /var/www/html/modules/ps_aneepay 2>/dev/null || true

echo "Install module via console ..."
php /var/www/html/bin/console prestashop:module install ps_aneepay 2>&1 | tail -3
php /var/www/html/bin/console prestashop:module enable ps_aneepay 2>&1 | tail -3

echo "Wiring AneePay (tables, hook, config) ..."
php /opt/enable_ps.php

chown -R www-data:www-data /var/www/html 2>/dev/null || true
chmod -R a+rwX /var/www/html/var /var/www/html/app 2>/dev/null || true

echo "PrestaShop ready: http://localhost:8084"
echo "Admin:            http://localhost:8084/admin (admin@example.com / admin1234)"
