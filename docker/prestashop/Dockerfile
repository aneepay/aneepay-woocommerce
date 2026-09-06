FROM prestashop/prestashop:8.1

# The official image's entrypoint runs the auto-install as www-data, which can't
# write the fresh named volume's var/cache (mode-bit issue in this environment).
# Baking world-writable dirs here makes the auto-install and runtime writes work.
RUN chmod -R a+rwX /var/www/html/var /var/www/html/app /var/www/html/config
