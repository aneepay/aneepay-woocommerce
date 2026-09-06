# AneePay CMS Integrations — монорепо

Плагины AneePay (криптооплата USDT/USDC/DAI на Polygon + Amoy) для трёх платформ в
едином репозитории. Каждый плагин изолирован в своей папке и собирается в
отдельный артефакт.

- **AneePay** — некастодиальная криптовалютная платёжная система, комиссия 0,5%,
  без KYC, hosted checkout, webhooks с HMAC-SHA256 подписью, sandbox (Amoy).
- Документация по API и модель платежа — [`INTEGRATION.md`](INTEGRATION.md).

## Плагины

| Платформа | Папка | Сборка | Артефакт | План |
| --------- | ----- | ------ | -------- | ---- |
| WooCommerce | [`plugins/woocommerce/aneepay`](plugins/woocommerce/aneepay) | `./scripts/pack.sh wc` | `dist/wc/aneepay.zip` | [`docs/ROADMAP.md`](docs/ROADMAP.md) |
| OpenCart 3.x | [`plugins/opencart/aneepay`](plugins/opencart/aneepay) | `./scripts/pack.sh oc` | `dist/oc/aneepay-<ver>.ocmod.zip` | [`docs/ROADMAP-OPENCART.md`](docs/ROADMAP-OPENCART.md) |
| PrestaShop 1.7/8 | [`plugins/prestashop/ps_aneepay`](plugins/prestashop/ps_aneepay) | `./scripts/pack.sh ps` | `dist/ps/ps_aneepay.zip` | [`docs/ROADMAP-PRESTASHOP.md`](docs/ROADMAP-PRESTASHOP.md) |

Статусы: WooCommerce — рабочая реализация; OpenCart и PrestaShop — каркас
(структура из соответствующих ROADMAP, реализация не начата).

## Сборка

```bash
./scripts/pack.sh wc     # или oc, ps, all
```

Скрипт линтит PHP (`php -l`), проверяет структуру/манифест платформы и кладёт
артефакт в `dist/<platform>/`. Версия берётся из `VERSION` (или из аргумента).
Каждая платформа упаковывает свой `zip` с правильным корневым путём.

## Локальная разработка

Стенд WooCommerce поднимается из [`docker/docker-compose.yml`](docker/docker-compose.yml):

```bash
cd docker
docker compose up --watch
# WooCommerce: http://localhost:8081  (admin / admin)
```

Контейнер монтирует `plugins/woocommerce/aneepay` напрямую — изменения в коде
подхватываются без пересборки. Для OpenCart/PrestaShop стенды добавляются
аналогично по мере реализации (см. `docs/ROADMAP-*.md`).

## Структура

```
.
├── INTEGRATION.md                     # API AneePay (единый источник правды)
├── VERSION                            # общая версия релизов
├── docs/                              # ROADMAP по каждой платформе
├── plugins/
│   ├── woocommerce/aneepay/           # WC-плагин (PHP)
│   ├── opencart/aneepay/              # OC-расширение (каркас)
│   └── prestashop/ps_aneepay/         # PS-модуль (каркас)
├── scripts/pack.sh                    # сборка/упаковка артефактов
├── docker/                            # локальный стенд (WooCommerce)
└── .github/workflows/release.yml      # CI: линт + упаковка + релиз
```

> При обработке [`INTEGRATION.md`](INTEGRATION.md) полезно следить за §8 (рецепт
> создания плагинов для любой CMS) — это общий каркас для всех трёх платформ.
