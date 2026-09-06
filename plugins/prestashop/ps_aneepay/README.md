# AneePay Crypto Gateway — PrestaShop 1.7 / 8.x

Status: **working implementation** (first pass; mirrors the WooCommerce plugin).
Reference — `../woocommerce/aneepay`. Plan — `../../docs/ROADMAP-PRESTASHOP.md`.

## Files

```
ps_aneepay/
  ps_aneepay.php                      (PaymentModule: install, hookPaymentOptions, getContent)
  classes/
    AneePayApi.php                    (create/get_payment, test_connection, signature, log)
    AneePayUsdConverter.php           (fiat -> USD -> token)
    AneePayOrder.php                  (ps_aneepay_order mapping)
    AneePayWebhook.php                (resolve order + apply OrderHistory)
  controllers/front/
    payment.php    (validateOrder + redirect to checkout_url)
    webhook.php    (STATUS_URL — HMAC verify, fail-closed)
    success.php / fail.php            (result pages)
    cron.php       (poll pending orders)
    check.php      (AJAX "I already paid" / polling)
  views/templates/front/result.tpl
  logo.png
  index.php                           (in every folder — PS requirement)
```

## Endpoints (set them in the AneePay account panel)

- `status_url` — `index.php?fc=module&module=ps_aneepay&controller=webhook`
- `success_url` — `index.php?fc=module&module=ps_aneepay&controller=success`
- `fail_url` — `index.php?fc=module&module=ps_aneepay&controller=fail`
- polling — `index.php?fc=module&module=ps_aneepay&controller=cron`

## Notes

- Setup: Modules → Module manager → AneePay → Configure. Enter the Account ID +
  Webhook Secret. Save. Order states are configurable (defaults: pending =
  "Awaiting payment", paid = "Payment accepted", failed/cancelled accordingly).
- Amount semantics: token amount = store total converted via Frankfurter/ECB
  (auto) + store `Currency` rate fallback + manual rate; rounded **up** (ceil).
- Order metadata is stored in the `ps_aneepay_order` table; the webhook resolves
  the order by `payment_id` / `operation_id` (128-bit int, kept exact) or `#N`.
- PrestaShop has no built-in cron: add the cron URL above to your server crontab.
