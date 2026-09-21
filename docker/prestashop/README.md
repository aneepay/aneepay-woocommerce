# AneePay PrestaShop 8.1 stand

Auto-installs PrestaShop 8.1 (official image) + the AneePay module.

> Status: **working stand.** The shop installs, the module is installed +
> enabled, its `paymentOptions` hook and the `aneepay_*` tables are created, and
> the module front controllers (`webhook`/`success`/`fail`/`cron`/`check`/
> `payment`) route correctly. The module still lacks the admin extras and the
> buyer "waiting" screen (see `docs/ROADMAP-PRESTASHOP.md`).

## Run

```bash
cd docker/prestashop
docker compose up -d --build
```

Wait for the `setup` container to print `PrestaShop ready`.

- Shop: http://localhost:8084
- Admin: http://localhost:8084/admin  (admin@example.com / admin1234)

## What it does

1. Starts MariaDB + the `prestashop/prestashop:8.1` image (a tiny wrapper
   `Dockerfile` bakes writable `var/`/`app/` dirs — the official image's
   auto-install crashes on the fresh volume otherwise).
2. Auto-installs the shop (`PS_INSTALL_AUTO=1`, `PS_INSTALL_DB=0` — the DB is
   pre-created by MariaDB).
3. A `setup` container waits for the install, fixes permissions, then:
   - `php bin/console prestashop:module install` + `enable` (registers the module),
   - `enable_ps.php` — directly creates `ps_aneepay_order`/`ps_aneepay_log`,
     registers the `paymentOptions` hook, sets config defaults, activates the
     module.

Known quirks hit while getting this working:
- The official image's auto-install fails on `var/cache` permissions (fixed).
- `PS_INSTALL_DB=1` breaks because MariaDB already creates the DB.
- Admin password must be ≥ 8 chars.
- `Module::install()` from a bare CLI fatal (`Language` context) — hence the
  DB-direct `enable_ps.php`.
- Module front controllers 404 unless the module is associated with the shop in
  `ps_module_shop` (the Dispatcher treats it as inactive otherwise) — the stand
  now inserts this association.
- The image's CLI PHP has no `mysqli` (only `pdo_mysql`) — the wait loop uses
  PDO.
