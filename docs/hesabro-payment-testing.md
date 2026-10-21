# Hesabro payment testing

The gateway sends POST to `https://api.hesabro.ir/@{client}/payment-service/wallet/user-charge?username={customer phone}` with JSON `amount` (IRR) and `callback_url`, JSON Accept/Content-Type headers, Basic Auth using the payment Client ID and Client Secret, and a stable `Idempotency-Key` derived from the payment authority. Set the payment client to `sabz-co` and both credentials to the supplied API key to match the example curl. Credentials are managed in admin Settings → Payments; accounting credentials are separate. Portal top-ups enforce a higher minimum than a direct 10,000 IRR test.

## Display a callback directly in the browser

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

The normal callback is `{APP_URL scheme}://{CUSTOMER_PORTAL_DOMAIN}/wallet/payments/{existing payment ID}/callback`. It requires an existing payment record. It is not suitable for an unrelated manual curl payment. Ensure APP_URL uses HTTPS in production (the checked local environment uses HTTP).

All Hesabro callbacks are retained in `payments.gateway_payload.callbacks`, including repeated callbacks, and shown in the existing admin Billing → Payments → payment details diagnostics panel. The latest verification error is also retained.

## Verification limitation

Hesabro completion previously accepted any callback that did not explicitly signal failure, even an empty request, and credited the wallet. Successful-looking callbacks now leave the payment pending and retain the response for review. Explicit failure callbacks still follow the existing failure path. Implement authenticated server-side verification with amount, currency, payment identity and settlement checks before enabling automatic Hesabro credit. The charge endpoint alone and a browser success status cannot establish settlement. The provided curl does not document a verification endpoint or signature contract, so none has been invented.
