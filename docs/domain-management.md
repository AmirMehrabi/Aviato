# Domain commerce

Aviato supports a selected-customer domain storefront, saved registration checkout, wallet/gateway funding, asynchronous registration, domain invoices, nameserver changes, manual renewal, and opt-in wallet-funded automatic renewal. Premium registration, transfers, DNSSEC, hosted DNS records, and automated redemption are not sold in this release.

## Customer journey

Customers open **مدیریت → دامنه‌ها** (`/domains`). Search results show the first-year retail total and current one-year renewal total when that extension is ready for sale. The three checkout stages are **Owner details → Nameservers → Review & pay**. Each completed stage is stored server-side. Saved registrant profiles and ownership snapshots are encrypted at rest; Iranian phone numbers entered in ordinary mobile format are normalized for the provider.

Checkout belongs to the signed-in customer's personal account and wallet, independently of the active workspace. Workspace members cannot access another customer's domains, orders, invoices, or domain-payment receipts. Checkout and gateway return redirects preserve supported customer-portal aliases.

Customers with historical orders or owned domains retain access to their history and management when their new-purchase access is revoked. Suspended customers remain blocked. Existing domains can still be manually or automatically renewed when the new-sales switches are off, provided renewal pricing is enabled and current.

## Admin setup

1. Configure the encrypted Reseller World token at **Settings → دامنه‌ها و دسترسی مشتریان** (`/settings/domains`). A blank token field preserves the existing secret.
2. Enable the domain section and select eligible customers. The selection flag is not customer mass-assignable.
3. Open **مدیریت قیمت‌ها، سفارش‌ها و گزارش مالی** (`/settings/domains/commerce`) and synchronize supplier prices.
4. Review retail prices. Newly discovered products receive a draft suggestion using a configurable 25% markup, rounded up to 1,000 toman, and stay disabled. Fixed retail prices survive future supplier-cost updates. Optional automatic pricing recalculates from markup and minimum profit.
5. Enable registration terms and **one-year renewal** for each offered extension. A missing renewal price, supplier data older than 24 hours, or insufficient margin blocks new quotes. All retail inputs are toman, excluding tax; supplier costs are stored in rials. Customer totals include the configured tax.
6. Optionally configure nameserver defaults, but only if those DNS servers are operational for customer domains. Otherwise customers enter their own DNS provider's nameservers.
7. Enable the separate **new purchases** switch. It defaults off. Turning it off does not remove access to already-owned domains or renewals.

No migration enables products, selects customers, changes supplier credentials, or enables sales. No live domain is registered by setup or price synchronization. Provider contact creation can send a verification email as part of a customer-authorized purchase.

## Pricing and money

- Supplier costs, retail prices, tax, currency, term, contact mode, and a 15-minute quote expiry are snapshotted on each order.
- Availability responses expose retail totals, not supplier costs or raw provider errors.
- Cached search results are advisory. The purchase flow performs uncached checks, including a domain-specific supplier price check before the billable submission. Premium or missing prices block payment.
- Changes requiring a new quote need explicit customer acceptance. An expired quote never silently increases a payment.
- Available funds exclude pending usage. Under a wallet lock, a reservation immediately reduces wallet balance and creates a `domain_reservation` debit, protecting the funded order from concurrent spending.
- A successful gateway callback credits the wallet and reserves the order in the same transaction. Domain gateway payments are always in IRR; IRT wallets receive converted credits. Callback retries do not credit or reserve twice.
- Closing the browser does not stop fulfillment. Late payments for cancelled, expired, or no-longer-eligible checkouts remain wallet credit and do not silently place the order.
- Successful fulfillment converts the reservation into a domain charge without another debit. It creates exactly one domain invoice with a base-price line and, where applicable, a separate tax line.
- A confirmed failure releases the reservation with one linked wallet-refund transaction. This is wallet credit, not an automatic bank refund. Successful registrations have no automatic cancellation/refund action.
- Usage invoices remain separate and ignore domain transactions. Domain invoices are accessible from orders, management, and **Billing → Domain invoices**. Existing verified top-up accounting synchronization remains in place; completing a domain order does not submit that deposit a second time to Hesabro. A dedicated CSV export links costs, orders, invoices, reservations, and releases. A separate external domain-revenue posting requires an agreed accounting mapping; this release does not invent one.

## Registrar operations and recovery

Base URL: `https://api.reseller.world/v1.5/multi_registrar`. Reference: <https://api.reseller.world/openapi.json>.

`DomainProvider` has bounded HTTP timeouts and no automatic mutation retries. Every mutation attempt is persisted before the network request. Successful submissions store `trackId`; jobs inquire through `/manage/inquiry/{trackId}`. Registration and renewal success additionally require the reseller-owned domain record and an expiry covering the full purchased period. Renewal must extend the previous expiry and retain the provider identity.

A timeout, crash during submission, missing tracking ID, or mismatched tracking result never triggers a second purchase. Uncertain orders retain their reservation and appear in the admin queue. Admins can attach a provider tracking ID only after verifying that its job and domain/contact match the order. A known tracked result can be inquired again safely. If the provider confirms a request was never executed and no tracking ID exists, an admin with wallet-management permission can record its support reference and release the reservation. This is audited; a connection error alone is not evidence of non-execution.

Nameserver changes have separate tracked operations. The UI retains the previous values until completion is confirmed. An uncertain update blocks further updates and appears in admin reconciliation. These are nameservers, not a hosted A/MX/TXT editor.

## Lifecycle

The existing Laravel scheduler runs:

- `domains:reconcile` every minute: dispatch funded/pending orders and inquire about nameserver operations. Killed submissions become operator-review cases.
- `domains:sync-prices` hourly: refresh costs, preserve fixed retail prices, and calculate opted-in automatic prices.
- `domains:lifecycle` daily at 08:30 Asia/Tehran: synchronize registrar expiry, send deduplicated 30/14/7/1-day reminders and expiry notices, and attempt authorized automatic renewals.

`ProcessDomainOrder` uses the existing default queue, unique jobs, and overlap prevention; no new Horizon supervisor is required. Keep the existing scheduler and queue workers running. Monitor the admin review queue, stale prices, queue failures, and supplier funding; the documented API has no dedicated balance endpoint used by this integration.

Automatic renewal requires explicit consent and a maximum total in toman, including tax. It attempts one year within seven days of expiry, using available wallet funds only, without initiating a bank/gateway payment. Prices over the cap, insufficient funds, and failures generate notifications. Uncertain renewal results never create a new attempt. Turning it off leaves the domain valid until expiry. Expired domains direct customers to support: registry grace periods and restoration are not assumed universally.

## Adding .ir

The product contact mode is snapshotted per order. International products use registrant profiles and provider contact handles; `registry_handle` products collect holder, administrative, technical, and billing handles without calling `/contact/create`. The latter explicitly excludes `.ir` in the API documentation.

An `.ir` catalog entry is discovered disabled. Before enabling it, verify supported periods, handle permissions, registrar behavior, and lifecycle rules with the provider, configure register/renew retail prices, choose handle mode, and acknowledge registry verification in the admin form. The same checkout, money, invoice, tracking, and renewal infrastructure is reused. Do not treat the presence of the adapter or fixtures as proof of a completed live `.ir` registration.

## Validation and rollout

`php artisan test --filter=Domain` covers access, aliases, normalized input, saved profiles, encrypted data, fixed/automatic prices, stale quotes, live-cost changes, funding, repeat callbacks, reservations, refunds, ambiguous submissions, invoices, management, nameserver confirmation, renewal, opt-in automation, and `.ir` handle checkout. External services are faked in tests.

The migration is also verified against the local MySQL database. Frontend assets are built with Vite; checkout is visually checked at desktop and mobile breakpoints with synthetic data. Production read-only price and availability calls confirm the live price shape and IRR values. A real paid registration requires an explicitly chosen domain and owner details; it is not performed by deployment.

## Multiple registration providers

Aviato now stores a provider identity on products, orders and owned domains. Existing records default to `reseller_world`. The product uniqueness key includes provider, extension, operation and period. The remote `provider_id` continues to identify the domain within that provider; it is not the provider selection.

New registrations select eligible enabled offers by the final retail price before common customer tax. `domains.routing_policy` accepts `cheapest` (lowest first-year retail price), `two_year` (registration plus one annual renewal), `reseller_world`, or `irpower`. `domains.provider_routes` contains optional extension-specific overrides. Both registration and the same provider's annual renewal must have valid prices. Quotes, payment, fulfillment and renewal remain pinned to their provider. A routing change never transfers a domain. Disabling a provider for new registrations does not disable its existing domains' renewals; its API credentials must remain configured.

### IRPOWER

- Endpoint: `https://resellerarea.net/api`; POST JSON commands, `X-Api-Key` header. The account key is stored encrypted under `domains.irpower_api_key` and excluded from flashed input and admin audit payloads. It is never rendered into the admin form or committed. Enable new registration routing with `domains.irpower_enabled`.
- `domains:import-irpower-prices` imports the account owner's supplied 2026-10-26 price quotation as draft offers, inheriting matching existing automatic markup and minimum-margin rules (otherwise suggesting 25% markup), with one-year terms and manual price validity through 2026-11-25, anchored to that quotation date. Existing prices are never overwritten or silently revalidated. `--activate-existing` enables newly imported offers for operations already approved on Reseller.world, and `.ir` registration and renewal. It does not enable all supplied extensions. The 19 quoted international transfer costs are retained as disabled future catalog entries. No `.ir` transfer cost was supplied, so none is invented.
- `.ir` registration and renewal cost is 108,000 toman before VAT, as confirmed by the owner. Supplier costs are stored in IRR; customer currency and tax continue through Aviato billing. The initial suggested retail is 135,000 toman before customer VAT, or 148,500 toman with 10% VAT. These are editable prices, not a guaranteed market price.
- Manual costs and their validity deadline are editable per provider offer in Settings → Domains → Commerce. An explicit confirmation is required when extending cost validity. Expired offers are excluded from routing, including renewal. The hourly Reseller.world sync cannot update IRPOWER prices.
- International availability and premium detection continue through Reseller.world. Its returned supplier price is never used as an IRPOWER cost. Its unsupported extensions (currently `.us`, `.co.uk`, `.nl`, `.de`, `.ca`, `.eu`, `.tw`) cannot proceed to automated purchase through this lookup path; their IRPOWER catalog offers stay draft unless deliberately reviewed, and availability still fails closed. Premium names are not automatically registered through IRPOWER.
- Registration sends inline contact details for international domains and IRNIC handle fields for `.ir`. Phone and postal fields are translated into provider format. Nameservers are restricted to 2–5 for IRPOWER and 2–6 for Reseller.world. IRPOWER responses normalize both JSON boolean and the documented string `"true"`/`"false"`, and both singular/plural error envelopes.
- IRNIC permission preflight (`GetContactInfo`) runs before progressing from owner details and again before a funded registration is submitted. Customers may use one handle for all roles or enter separate roles. Existing saved profile roles are preserved.
- Registrar mutations are persisted before submission and never retried automatically. Explicit provider rejection releases the reservation once. An ambiguous HTTP response, timeout or process interruption retains funds for reconciliation. There is no cross-provider mutation failover.
- **Documented API limitation:** IRPOWER supplies no order inquiry, domain expiry or authoritative status API. A successful registration/renewal response is stored as accepted, and the customer sees confirmation in progress. Admins with wallet permissions must verify the domain owner, completed registration/renewal and actual expiry in their reseller account, then enter expiry and supporting reference in the IRPOWER confirmation form. Aviato verifies account access with `GetDomainWhoisInfo` and `GetDomainNameServers` before issuing the invoice and completing the order. Dates are never guessed from WHOIS or calculated and presented as authoritative. Ambiguous orders may also be confirmed from account evidence, or released only after provider confirmation of non-execution through the existing audited resolution flow.
- IRPOWER nameserver changes are read back using `GetDomainNameServers`. Unknown outcomes are reconciled by read-only lookup, never by repeating the change. The daily sync refreshes nameservers and preserves the last confirmed expiry. External expiry changes can be recorded with supporting reference in the admin commerce page; funded orders block concurrent expiry edits.

### .ir availability

Use the official registry service `whois.nic.ir:43`, which was verified reachable with registered and unregistered examples. The implementation accepts only a strict second-level ASCII `.ir` name, uses a fixed host and port, bounded responses and timeouts, a 30-second result cache, and a shared 30-per-minute registry request limit in addition to the existing per-customer request limit.

Only an exact matching domain record or the registry's explicit `%ERROR:101: no entries found` response produces an availability result. Malformed output, rate-limit messages, disconnections, truncated output and timeouts are unknown, never “available.” IRNIC’s published WHOIS policy is at https://www.nic.ir/docs/ir/annex-2-whois-guideline. Public WHOIS is preliminary: reserved names, holder eligibility and IRNIC final approval may still prevent registration. The UI explains this before checkout. DNS absence is never used as an availability signal, public WHOIS does not establish provider ownership, and it is not an expiry confirmation source.

### Verification and rollout

Use fixtures for purchases and payment callbacks. Live validation is limited to read-only authentication, registry lookup and catalog retrieval; no live registration or renewal is needed to deploy the integration. Configure both credentials, import IRPOWER offers, review supplier tax assumptions and customer prices, and set `.ir` routing to `irpower`. Retain selected-customer access and the global domain sales switch.
