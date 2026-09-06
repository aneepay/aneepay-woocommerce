# Plugins — реализации AneePay для CMS

Монорепо содержит по одной папке на платформу. Код полностью изолирован:
каждую папку можно упаковать в отдельный артефакт и релизить независимо.

| Папка                          | Платформа | Упаковка (`scripts/pack.sh`) | Артефакт |
| ------------------------------ | --------- | -------------------------- | -------- |
| `plugins/woocommerce/aneepay/` | WooCommerce | `./scripts/pack.sh wc`  | `dist/wc/aneepay.zip`        |
| `plugins/opencart/aneepay/`    | OpenCart 3.x | `./scripts/pack.sh oc` | `dist/oc/aneepay.ocmod.zip` |
| `plugins/prestashop/ps_aneepay/` | PrestaShop 1.7/8 | `./scripts/pack.sh ps` | `dist/ps/ps_aneepay.zip`    |

Общие материалы:

- `INTEGRATION.md` — единый источник правды по API AneePay.
- `docs/ROADMAP*.md` — планы по каждой платформе.
- `VERSION` — общая версия релизов (переопределяется флагом или тегом).
