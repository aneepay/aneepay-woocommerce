#!/bin/sh
# AneePay WooCommerce test shop provisioning.
# Runs inside the `setup` wp-cli container as root; fixes ownership at the end.
set -e

echo "Waiting for WordPress config ..."
until [ -f /var/www/html/wp-config.php ]; do
	sleep 2
done

if ! wp core is-installed --allow-root >/dev/null 2>&1; then
	echo "Installing WordPress core ..."
	wp core install \
		--allow-root \
		--url="http://localhost:8081" \
		--title="AneePay Shop" \
		--admin_user=admin \
		--admin_password=admin \
		--admin_email=admin@example.com \
		--skip-email
fi

echo "Installing WooCommerce ..."
wp plugin install woocommerce --activate --allow-root

echo "Activating AneePay gateway ..."
wp plugin activate aneepay --allow-root

# Make the PHP-FPM/Apache user able to write uploads etc. ONLY for directories
# inside the wp_core volume. Never chown the bind-mounted plugin folder
# (wp-content/plugins/aneepay) — that would change ownership on the host!
upload_dir="$(wp option get upload_path --allow-root 2>/dev/null || echo '/var/www/html/wp-content/uploads')"
chown -R www-data:www-data "${upload_dir}" 2>/dev/null || true
chown www-data:www-data /var/www/html/wp-content 2>/dev/null || true

echo "Setup complete: http://localhost:8081  (admin / admin)"
