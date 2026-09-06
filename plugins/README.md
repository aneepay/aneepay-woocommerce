# Plugins — AneePay implementations for CMSs

The monorepo has one folder per platform. The code is fully isolated: each
folder can be packaged into its own artifact and released independently.

| Folder | Platform | Package (`scripts/pack.sh`) | Artifact | Status |
| ------------------------------ | --------- | -------------------------- | -------- | ------ |
| `plugins/woocommerce/aneepay/` | WooCommerce | `./scripts/pack.sh wc`  | `dist/wc/aneepay.zip`        | working |
| `plugins/opencart/aneepay/`    | OpenCart 3.x | `./scripts/pack.sh oc` | `dist/oc/aneepay.ocmod.zip` | working (v1) |
| `plugins/prestashop/ps_aneepay/` | PrestaShop 1.7/8 | `./scripts/pack.sh ps` | `dist/ps/ps_aneepay.zip`    | working (first pass) |

Shared materials:

- `docs/INTEGRATION.md` — the single source of truth for the AneePay API.
- `docs/ROADMAP*.md` — per-platform plans.
- `VERSION` — the shared release version (overridable by a flag or tag).
