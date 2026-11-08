# Homepage domain search

The approved homepage includes the search form at `/`. The old `/preview/home`
URL redirects to `/`. Its CSRF-protected POST endpoint is `/domains/check`. A successful, purchasable search returns
a portal URL with `domain`, `register=1` and `from=homepage`. Authentication preserves
that GET destination, and the portal automatically checks the selected domain once.
The user still selects a registration period and confirms the existing checkout;
searching never creates an order or takes payment.

Only available, non-premium names with an enabled, valid retail registration and
renewal price are handed off. Premium/unknown prices, unavailable names, disabled
sales and unsupported suffixes remain on the homepage with guidance. `.ir` results
are preliminary WHOIS results, not a registration guarantee. Domain access and
workspace ownership checks continue to apply after login. Accounts without domain
purchase access receive a support link; search does not grant that permission.

## Quotas

`config/domain-search.php` contains **local safety budgets, not verified provider
allowances**. Confirm the account's actual rate and burst allowances with the
provider and set the corresponding variables from `.env.example` accordingly.
No public numeric Reseller World allowance was established for this implementation.
Other applications sharing the provider account must also share this budget or have
separate allocated quotas; this application cannot count their outbound requests.

- Visitors: 10 requests/minute and 60/hour/IP, including cache hits.
- Reseller World: 30 outbound requests per rolling minute, at most 15 for search;
  up to 10 requests in a rolling second. Remaining minute capacity is available to
  checkout, fulfillment, management and catalog requests, all via the shared gate.
- IRNIC: 30 registry requests/minute, at most 20 for search, burst 5/second.
- IRPOWER: 30 requests/minute, burst 10/second; public search uses WHOIS or
  Reseller World, rather than calling IRPOWER.

Use a persistent shared cache (`DOMAIN_PROVIDER_CACHE_STORE`, preferably database
or Redis) across all web and queue instances. Atomic locks serialize quota updates.
Per-domain locks coalesce simultaneous searches; valid answers are reused for 30
seconds across homepage and portal. Cache hits don't consume provider capacity.
Ephemeral array/null/Octane/failover stores are rejected outside tests. A broken
budget store prevents sending a request, rather than bypassing the limits.

A provider HTTP 429 sets a shared cooldown using `Retry-After` (seconds or HTTP
date; 60 seconds if absent). A later short cooldown cannot shorten an existing one.
Cached answers can still be served. No browser background polling or automatic
provider retries are performed. The form retains the domain, displays a countdown,
and lets the visitor retry deliberately. A timeout/error is never an unavailable
or available result. Provider costs, credentials and error bodies are not public.

Paid orders blocked before a provider request remain eligible for the existing
`domains:reconcile` schedule, without refunds or additional payment. A refused local
mutation leaves no uncertain remote operation. Once a mutation has been sent, an
unconfirmed response remains subject to the existing reconciliation/manual review
rules; it is never blindly resubmitted. Keep the scheduler and queue worker running.

## Verification

- `php artisan test --filter='PublicDomainSearchTest|DomainCommerceTest|DomainProviderRoutingTest|DomainAvailabilityTest|DomainWorkspaceTest|MarketingHomeTest'`
- `node --test tests/Frontend/domain-search.test.mjs`
- `npm run build`

Provider calls in tests are faked. Do not test registration or payment against real
accounts as part of UI verification.
