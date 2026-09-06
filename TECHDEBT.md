# Tech debt — shared AneePay core (TODO)

Status: **planned, not started.** We deliberately kept per-platform copies for
now; this note records what must be extracted later.

## Problem

The platform-agnostic logic is duplicated across plugins:

| Logic | WooCommerce | OpenCart | PrestaShop |
| ----- | ----------- | -------- | ---------- |
| API client (create/get_payment, request, errors) | `plugins/woocommerce/aneepay/includes/class-crypto-api-handler.php` | `plugins/opencart/aneepay/system/library/aneepay/aneepay.php` | (not yet) |
| Fiat→USD→token conversion | `class-usd-converter.php` | `usd-converter.php` | (not yet) |
| Webhook HMAC-SHA256 verify | `aneepay_verify_webhook_signature()` | `AneePay_Client::verify_signature()` | (not yet) |
| `operation_id`→UUID (128-bit) | `aneepay_operation_id_to_uuid()` | `AneePay_Client::operation_id_to_uuid()` | (not yet) |

Only the "pure logic" (conversion math, signature, operation_id) can be shared.
The CMS glue (order, HTTP, currency, settings, cron, templates) **must** remain
per-platform.

## Target layout (proposal)

```
shared/
  src/
    AneePayApiClient.php     # create/get_payment/test_connection/request/errors
    AneePayUsdConverter.php  # fiat -> USD -> token (ceil, rate sources)
    AneePaySignature.php     # HMAC-SHA256 verify (fail-closed)
    AneePayOperationId.php   # operation_id -> UUID
  contracts/
    HttpTransportInterface.php  # POST/GET  -> Wc|Oc|Ps implementations
    StoreInterface.php          # currency / rate
    SettingsInterface.php       # account_id/secret/token/network/sandbox
    OrderInterface.php          # create/find/update status + meta
  exceptions/
```

Each plugin becomes a thin shell that feeds settings into the core and implements
the `StoreInterface`/`OrderInterface`. `scripts/pack.sh` copies `shared/` into
each build artifact at packaging time.

## Why now / how to do it later

- Do it **while the plugins are few** (2 so far) — a later extraction costs more.
- Requires refactoring WooCommerce first (it is tightly coupled to `WP_*`/`WC_Order`).
- Update `scripts/pack.sh` so every platform bundles the core.

## Related

- `docs/INTEGRATION.md` (local, gitignored) — single source of truth for the API.
- Per-plugin roadmaps in `docs/` (local, gitignored).
