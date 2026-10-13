# Automatic IPDR integration

Aviato owns VM creation, IP assignment history, prices, and billing. IPDR automatically creates its metering records from Aviato inventory; no second VM provisioning action is necessary.

## Activate after deploying both applications

1. Deploy Aviato first, including `php artisan migrate --force`. Deploy IPDR and run its migrations too. The added tables store durable receipts and delivery progress; IPDR also adds an indexed nullable delivery acknowledgement timestamp to usage revisions.
2. Keep the existing IPDR usage-read token and Aviato inventory-read token configured. Create a separate random secret (for example `openssl rand -hex 32`) and set the **same** `IPDR_INTEGRATION_TOKEN` in both applications. Keep it out of source control and logs. This secret authenticates inventory notifications and usage delivery, and does not replace either read token.
3. Run `php artisan config:cache` and restart queue workers in both applications. Use an asynchronous queue (`database` or `redis`) and a shared cache supporting locks. IPDR queue retry-after must exceed the longest job timeout (300 seconds); the new default is 1,200 seconds. Preserve any explicit overrides accordingly.
4. Ensure each application's Laravel scheduler runs every minute. Ensure IPDR's collector continues running and its exporter can reach it.
5. In Aviato, open **billing / network / همگام‌سازی شبکه** and choose **همگام‌سازی اکنون**. Verify inventory acknowledgement, collector activity, delivery backlog, and the VM registration statuses. A new VM becomes eligible once it has an immutable UUID and an assigned IP.

For IPDR, a worker can be supervised with `php artisan queue:work --tries=3 --timeout=300`. Aviato can use its existing Horizon/queue worker configuration. Do not add duplicate scheduler cron entries if deployment already manages scheduling.

## Normal operation

VM/IP changes trigger an after-commit notification that queues an inventory refresh in IPDR. IPDR still polls inventory every minute to recover dropped notifications. It imports a snapshot first, then cursor-based changes. IP reassignment closes the previous assignment while retaining history.

Published usage revisions form the durable outgoing ledger. Delivery sends up to 100 records per batch. Aviato persists the entire batch before acknowledging receipt. IPDR marks only acknowledged rows as delivered, so a retry, a lost HTTP response, or a later-committing older stream row cannot silently skip delivery. Delivery metadata does not change immutable payload timestamps. Pending deliveries are protected from history pruning while direct delivery is configured.

Aviato processes persisted batches in background jobs. Invalid/conflicting records remain in the receipt with errors for review; they do not block valid records. Database failures retry the job rather than treating data as invalid. Billing runs separately using the existing revision-based rating logic; receipt is not confirmation of a charge. Repeating push or pull records does not duplicate a revision or a charge.

Aviato polls the usage API every minute as recovery and to refresh IPDR health. The scheduler re-dispatches receipts that remain queued. Received pending/quarantined records are retried for rating every minute. Failed runs and rejected records remain visible in activity even when newer syncs succeed; **پردازش دوباره** retries them after the underlying problem is fixed.

## UI and freshness

The page refreshes status without replacing date inputs. It displays Tehran time, queues work without waiting for the network, and shows registration separately from usage receipt. A queued job older than two minutes prompts an operator to check the worker/scheduler. Running jobs older than ten minutes are marked interrupted so they can be retried.

No observed traffic is not proof of zero usage. Closed hourly usage normally appears after the hour ends plus IPDR's finalization delay (default ten minutes). Collector silence, partial records, or missing records require investigation rather than a zero-usage label. Registration acknowledgements use IPDR's successful inventory cursor, compared to the latest event for each active Aviato assignment.

Backfill inputs accept Tehran local time and are converted to UTC. Both dates are required. Each backfill page after the first sends only its opaque cursor. Large backfills continue in durable jobs and do not overwrite the incremental checkpoint. Expired incremental cursors replay the retained change stream with deduplication; data already pruned by IPDR cannot be reconstructed by cursor recovery. Use the existing reconciliation tools for historical discrepancies.

## Endpoints

- IPDR `POST /api/internal/v1/integration/inventory-sync`: shared integration token; queues registration refresh.
- IPDR `GET /api/v1/integration/status`: existing usage-read token; inventory, collection, finalization and delivery health. No secrets or customer financial data are returned.
- Aviato `POST /api/internal/v1/metering/usage`: shared integration token; `{batch_id, items}`. `batch_id` is lowercase SHA-256 of the items JSON using unescaped slashes and Unicode. Maximum 100 items. HTTP 202 with `{accepted: true, batch_id, status}` acknowledges durable receipt.

Existing usage buckets and summaries APIs remain available. Delivery and inventory retry automatically; an operator can also run `php artisan ipdr:deliver-aviato-usage` or `php artisan ipdr:sync-aviato-inventory` on IPDR, and `php artisan network-usage:sync` on Aviato.
