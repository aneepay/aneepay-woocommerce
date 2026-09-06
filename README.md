# AneePay CMS Integrations — Monorepo

AneePay plugins (crypto payments for USDT/USDC/DAI on Polygon + Amoy) for three
platforms in a single repository. Each plugin lives in its own folder and is
packaged into a separate artifact.

- **AneePay** — a non-custodial crypto payment system with a 0.5% fee, no KYC,
  hosted checkout, HMAC-SHA256-signed webhooks, and a sandbox (Amoy).
- API documentation and the payment model are in [`INTEGRATION.md`](INTEGRATION.md).

## Plugins

| Platform | Folder | Build | Artifact | Roadmap |
| --------- | ------ | ----- | -------- | ------- |
| WooCommerce | [`plugins/woocommerce/aneepay`](plugins/woocommerce/aneepay) | `./scripts/pack.sh wc` | `dist/wc/aneepay.zip` | [`docs/ROADMAP.md`](docs/ROADMAP.md) |
| OpenCart 3.x | [`plugins/opencart/aneepay`](plugins/opencart/aneepay) | `./scripts/pack.sh oc` | `dist/oc/aneepay-<ver>.ocmod.zip` | [`docs/ROADMAP-OPENCART.md`](docs/ROADMAP-OPENCART.md) |
| PrestaShop 1.7/8 | [`plugins/prestashop/ps_aneepay`](plugins/prestashop/ps_aneepay) | `./scripts/pack.sh ps` | `dist/ps/ps_aneepay.zip` | [`docs/ROADMAP-PRESTASHOP.md`](docs/ROADMAP-PRESTASHOP.md) |

Status: WooCommerce is a working implementation; OpenCart and PrestaShop are
skeletons (structure per the matching ROADMAP, implementation not started).

## Build

```bash
./scripts/pack.sh wc     # or oc, ps, all
```

The script lints PHP (`php -l`), validates the platform structure/manifest and
writes the artifact to `dist/<platform>/`. The version is read from `VERSION`
(or passed as an argument). Each platform is packaged with the correct root path.

## Local development

The WooCommerce test shop is up from [`docker/docker-compose.yml`](docker/docker-compose.yml):

```bash
cd docker
docker compose up --watch
# WooCommerce: http://localhost:8081  (admin / admin)
```

The container bind-mounts `plugins/woocommerce/aneepay` directly, so code changes
are picked up without a rebuild. OpenCart/PrestaShop test shops are added the same
way as they are implemented (see `docs/ROADMAP-*.md`).

## Structure

```
.
├── INTEGRATION.md                     # AneePay API (single source of truth)
├── VERSION                            # shared release version
├── docs/                              # ROADMAP per platform
├── plugins/
│   ├── woocommerce/aneepay/           # WC plugin (PHP)
│   ├── opencart/aneepay/              # OC extension (skeleton)
│   └── prestashop/ps_aneepay/         # PS module (skeleton)
├── scripts/pack.sh                    # build / package artifacts
├── docker/                            # local test shop (WooCommerce)
└── .github/workflows/release.yml      # CI: lint + package + release
```

> When working from [`INTEGRATION.md`](INTEGRATION.md), see §8 (the recipe for
> creating plugins for any CMS) — it is the common foundation for all three
> platforms.
