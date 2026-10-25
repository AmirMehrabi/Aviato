# Domain availability pilot

Customers selected by an administrator can open **دامنه‌ها** in the customer portal and check domain availability. The page supports pasted HTTP/HTTPS website addresses, internationalized names when PHP Intl is available, and defaults extensionless names to `.com`. Purchase, renewal, transfer, and DNS management are future work; this release makes no registration or payment calls and does not show reseller costs as retail quotes.

## Enable access

1. Run the migration `2026_10_25_120000_add_domain_management_access_to_customers.php` when deploying.
2. In the admin portal, open **Settings → دامنه‌ها و دسترسی مشتریان** (`/settings/domains`).
3. Supply the Reseller World token if it is not already configured. A blank token field preserves the existing token.
4. Turn on the domain section, then search for customers and enable access individually. Use the selected-customers filter to review or revoke access.

All existing and new customers default to no access. Turning off the section preserves the selection but blocks everyone. Suspended customers are blocked even if selected. Access belongs to the signed-in customer, independently of the active workspace. Both the page and lookup endpoint enforce the restriction server-side. Admin configuration requires the existing settings-management permission; changes use the existing admin audit trail.

## Provider integration

- Specification: https://api.reseller.world/openapi.json
- Base URL: `https://api.reseller.world/v1.5/multi_registrar`
- Request: `POST /domain/{domain}/check`, JSON `{"period":1,"type":"register"}`.
- Valid results must contain a matching `domain` and a boolean `available`. Premium status is optional. Provider prices, raw errors, and credentials are excluded from customer responses.
- Unsupported-extension errors are mapped to a Persian validation message. Connection failures, invalid responses, and authentication failures produce a retryable service error and never imply a domain is available.
- Successful results are cached for 30 seconds, separately for each domain and token. Every request still checks customer access. Requests are limited to 15 per minute per authenticated customer.
- Connection timeout: 5 seconds; overall provider timeout: 12 seconds. The browser also times out requests and provides a retry message.

The token lives in the existing `app_settings` table under `domains.reseller_token`, encrypted using Laravel's application key. The global switch is `domains.enabled`; individual access is `customers.domain_management_enabled`. Tokens are never rendered into forms, flashed into validation sessions, or recorded in plain text in admin audit inputs. Credentials must be configured separately in each environment; they are absent from source code and migrations.

## Verification

`php artisan test --filter=DomainAvailabilityTest` covers authorization, navigation, normalization, failure states, unsupported extensions, caching, revocation, throttling, and encrypted/masked credential handling. Provider requests are faked in the test suite.
