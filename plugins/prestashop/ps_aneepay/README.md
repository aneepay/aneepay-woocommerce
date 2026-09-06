# AneePay Crypto Gateway — PrestaShop 1.7 / 8.x (черновик)

Статус: **каркас, реализация не начата.** План — `../../docs/ROADMAP-PRESTASHOP.md`.
Референс-реализация — `../woocommerce/aneepay`.

## Требуемая структура модуля

```
ps_aneepay/
  ps_aneepay.php                          (extends PaymentModule)
  classes/AneePayApi.php
  classes/AneePayUsdConverter.php
  classes/AneePayWebhook.php
  classes/AneePayOrder.php                (ps_aneepay_order)
  controllers/front/payment.php           (создание заказа + редирект)
  controllers/front/webhook.php           (STATUS_URL)
  controllers/front/success.php           (SUCCESS_URL)
  controllers/front/fail.php              (FAIL_URL)
  controllers/front/cron.php              (поллинг)
  views/templates/front/payment_pending.tpl
  views/templates/front/payment_result.tpl
  views/templates/front/payment_option.tpl
  logo.png
  index.php                               (в каждой папке — требование PS)
```

## Ключевые моменты

- `status_url` / `success_url` / `fail_url` —
  `index.php?fc=module&module=ps_aneepay&controller=webhook|success|fail`.
- Заказ валидируется через `validateOrder()`; меты — в таблице `ps_aneepay_order`.
- Смена статуса — `OrderHistory`; нужен маппинг «Ожидание оплаты» / «Оплачен» /
  «Отменён» по конфигу изделия.
- Поллинг запускается внешним cron на `.../cron`.
- Курс валюта→USD: Frankfurter/ECB + `Currency` fallback + ручной курс.
