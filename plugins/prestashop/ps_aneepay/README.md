# AneePay Crypto Gateway — PrestaShop 1.7 / 8.x (skeleton)

Status: **skeleton, implementation not started.** Plan — `../../docs/ROADMAP-PRESTASHOP.md`.
Reference implementation — `../woocommerce/aneepay`.

## Required module structure

```
ps_aneepay/
  ps_aneepay.php                          (extends PaymentModule)
  classes/AneePayApi.php
  classes/AneePayUsdConverter.php
  classes/AneePayWebhook.php
  classes/AneePayOrder.php                (ps_aneepay_order)
  controllers/front/payment.php           (create order + redirect)
  controllers/front/webhook.php           (STATUS_URL)
  controllers/front/success.php           (SUCCESS_URL)
  controllers/front/fail.php              (FAIL_URL)
  controllers/front/cron.php              (polling)
  views/templates/front/payment_pending.tpl
  views/templates/front/payment_result.tpl
  views/templates/front/payment_option.tpl
  logo.png
  index.php                               (in every folder — required by PS)
```

## Key points

- `status_url` / `success_url` / `fail_url` —
  `index.php?fc=module&module=ps_aneepay&controller=webhook|success|fail`.
- The order is validated via `validateOrder()`; metadata lives in the
  `ps_aneepay_order` table.
- Status change — `OrderHistory`; a mapping to "Awaiting payment" / "Payment
  accepted" / "Canceled" is configured.
- Polling is driven by an external cron calling `.../cron`.
- Currency→USD rate: Frankfurter/ECB + `Currency` fallback + manual rate.
