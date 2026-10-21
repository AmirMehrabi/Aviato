# Hesabro payment testing

The gateway sends POST to `https://api.hesabro.ir/@{client}/payment-service/wallet/user-charge?username={customer phone}` with JSON `amount` (IRR) and `callback_url`, JSON Accept/Content-Type headers, Basic Auth using the payment Client ID and Client Secret, and a stable `Idempotency-Key` derived from the payment authority. Set the payment client to `sabz-co` and both credentials to the supplied API key to match the example curl. Credentials are managed in admin Settings → Payments; accounting credentials are separate. Portal top-ups enforce a higher minimum than a direct 10,000 IRR test.

## Display a callback directly in the browser

Debug endpoints are disabled by default. Enable `HESABRO_DEBUG_CALLBACKS_ENABLED=true` only while testing, then clear the configuration cache through the normal deployment process. These endpoints cannot complete payments.

Use `https://aviato.ir/api/v1/hesabro/wallet/callback` as `callback_url` (no UUID or expiry). GET and POST responses show the current request method, query parameters, parsed body and raw body as formatted JSON. This preview does not persist data or update wallets. The separate capture sessions below remain available for persistent review.

## Capture a direct curl payment

On the host serving the application, under the application runtime user, run:

```sh
php artisan hesabro:callback-test --base-url=https://aviato.ir
```

Use the printed URL as the curl JSON `callback_url`, without a trailing slash. The URL accepts GET and POST, requires no login, and expires after 24 hours. Each new invocation without a token creates a separate session. The UUID is a capture capability; keep it private. This endpoint only records incoming data and does not update payments or wallets. Deploy the routes and controller first, and clear any stale route cache through the normal deployment process.

Review on the same host:

```sh
php artisan hesabro:callback-test YOUR_PRINTED_UUID
```

Captures are JSON lines in `storage/app/private/hesabro-tests/{UUID}/callbacks.jsonl`, including timestamps, method, content type, query parameters, parsed body and raw body. Authorization and cookie headers are not captured. Sessions and captures remain for review after expiry; delete the session directory when finished. Storage must be writable by the application runtime user. Multi-instance deployments need shared local-disk storage or requests routed to the instance that created the session.

## Application payment callback

The normal callback is `{APP_URL scheme}://{CUSTOMER_PORTAL_DOMAIN}/wallet/payments/{existing payment ID}/callback?expires=...&signature=...`. Each initiated payment receives a signed URL valid for 24 hours; unsigned, expired or altered URLs are rejected before recording or processing a callback. Only Hesabro-added `order_id`, `model_id` and `success` query fields are excluded from the URL signature. Those fields remain untrusted. Stored provider identifiers from the charge response, when available, must match. It requires an existing payment record. It is not suitable for an unrelated manual curl payment. Ensure APP_URL uses HTTPS in production (the checked local environment uses HTTP).

All Hesabro callbacks are retained in `payments.gateway_payload.callbacks`, including repeated callbacks, and shown in the existing admin Billing → Payments → payment details diagnostics panel. The latest verification error is also retained.

## Completion policy

A signed callback with numeric `order_id`, numeric `model_id`, and `success=1` completes the payment and credits the wallet once. The customer is redirected to the portal wallet and sees the same success message and receipt as Mellat. `success=0` marks a pending payment failed and shows the normal failure message. Repeated callbacks cannot credit the same payment twice or downgrade a successful payment. Callback history retains the last 20 requests.

This policy intentionally trusts the browser callback result, as requested. The signed URL binds the request to an initiated payment and expires after 24 hours, but it does not authenticate the payment outcome. A payer who obtains their callback URL can alter `success` or substitute identifiers if Hesabro did not return identifiers during initiation. Matching identifiers and using a unique URL do not prove settlement. There is no independent Hesabro verification request. An authenticated transaction-status API or provider-signed outcome is required to remove that fraud risk.
