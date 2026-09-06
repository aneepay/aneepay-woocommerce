# AneePay Crypto Gateway — OpenCart 3.x (skeleton)

Status: **skeleton, implementation not started.** Plan — `../../docs/ROADMAP-OPENCART.md`.
Reference implementation — `../woocommerce/aneepay`.

## Required extension structure

```
aneepay/                                  (contents of this zip / ocmod)
  admin/controller/extension/payment/aneepay.php
  admin/language/en-gb/extension/payment/aneepay.php
  admin/model/extension/payment/aneepay.php     (oc_aneepay_log table)
  admin/view/template/extension/payment/aneepay.twig
  catalog/controller/extension/payment/aneepay.php  (index/confirm/callback)
  catalog/model/extension/payment/aneepay.php       (oc_aneepay_order)
  catalog/view/theme/default/template/extension/payment/aneepay.twig
  catalog/language/en-gb/extension/payment/aneepay.php
  system/library/aneepay/aneepay.php   (API client + log)
  system/library/aneepay/usd-converter.php
  system/library/aneepay/webhook.php
install.xml  (ocmod manifest)
```

## Key points

- `status_url` / `success_url` / `fail_url` — routes
  `index.php?route=extension/payment/aneepay/callback|success|fail`.
- Order metadata — the `oc_aneepay_order` table (equivalent of WooCommerce meta).
- OpenCart has no WP-Cron: polling is driven by an external cron calling the
  `.../cron` controller; the cron line is shown in the admin.
- Currency→USD rate: Frankfurter/ECB + `oc_currency` fallback + manual rate.
