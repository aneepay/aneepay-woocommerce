# AneePay OpenCart 3.0.3.8 stand

Headless-installs OpenCart 3.0.3.8 + the AneePay payment extension, and pre-enables
the method so it shows in the checkout.

## Run

```bash
cd docker/opencart
docker compose up --build
```

Wait for the `setup` container to print `OpenCart ready: http://localhost:8082`.

- Store: http://localhost:8082
- Admin: http://localhost:8082/admin  (admin / admin)

## What it does

1. Starts MariaDB + an Apache/PHP 7.4 container with OpenCart 3.0.3.8.
2. Runs OpenCart's bundled `cli_install.php` headlessly (creates the DB schema,
   the admin user and `config.php`/`admin/config.php`).
3. Copies the AneePay extension (`plugins/opencart/aneepay`) into the store and
   creates its `aneepay_order`/`aneepay_log` tables.
4. Pre-enables the payment method (registers `oc_extension` + sets defaults,
   sandbox/Test mode on).
5. Removes the `install/` directory.

Next: in Admin → Extensions → Payments → AneePay → Edit, enter your AneePay
`Account ID` and `Webhook Secret` (or keep Test mode) and click "Test connection".
