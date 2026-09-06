# AneePay Crypto Gateway — OpenCart 3.x (черновик)

Статус: **каркас, реализация не начата.** План — `../../docs/ROADMAP-OPENCART.md`.
Референс-реализация — `../woocommerce/aneepay`.

## Требуемая структура расширения

```
aneepay/                                  (содержимое этого zip / ocmod)
  admin/controller/extension/payment/aneepay.php
  admin/language/en-gb/extension/payment/aneepay.php
  admin/model/extension/payment/aneepay.php     (таблица oc_aneepay_log)
  admin/view/template/extension/payment/aneepay.twig
  catalog/controller/extension/payment/aneepay.php  (index/confirm/callback)
  catalog/model/extension/payment/aneepay.php       (oc_aneepay_order)
  catalog/view/theme/default/template/extension/payment/aneepay.twig
  catalog/language/en-gb/extension/payment/aneepay.php
  system/library/aneepay/aneepay.php   (API-клиент + лог)
  system/library/aneepay/usd-converter.php
  system/library/aneepay/webhook.php
install.xml  (манифест ocmod)
```

## Ключевые моменты

- `status_url` / `success_url` / `fail_url` — маршруты
  `index.php?route=extension/payment/aneepay/callback|success|fail`.
- Меты заказа — таблица `oc_aneepay_order` (аналог мет WooCommerce).
- В OC нет WP-Cron: поллинг запускается внешним cron на контроллер `.../cron`,
  строка для которого показана в админке.
- Курс валюта→USD: Frankfurter/ECB + `oc_currency` fallback + ручной курс.
