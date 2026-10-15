# Portal performance rollout

## Baseline and scope

Production audit on 2026-10-15: Ubuntu 24.04, PHP 8.5, four CPUs,
8 GB RAM, MySQL, Redis, Horizon and Supervisor. Application cache and sessions
currently use the database. The default PHP-FPM pool allows five children.
Redis already carries queues, so disposable portal data needs a separate instance.

The first rollout targets admin finance summaries and warning counts, customer
dashboard monthly estimates, admin customer financial summaries, and admin network usage aggregates. Live VM rows,
wallet balances, authorization, payment processing, provisioning decisions and
console credentials continue to use authoritative data. Further pages will be
prioritized from measured query counts and slow queries.

## Implementation order

1. Record cold/warm query counts and timings using representative fixtures; record
   production health, memory, queue status and active service configuration.
2. Add an optional portal cache store with bounded TTLs, versioned keys,
   regeneration locks and database recomputation when the optional cache fails.
   Store arrays and scalars without enabling PHP object deserialization.
3. Cache admin aggregates for 15 seconds and customer estimates for 30 seconds.
   Include period/date filters and permission scope in keys; check permissions
   before reading cached data. Invalidate on relevant Eloquent writes, including
   after transaction commit. Bulk SQL updates and external writers are bounded
   by TTL unless they explicitly invalidate the scope.
4. Run Redis for disposable portal data on loopback port 6380 with 256 MB and
   allkeys-lfu eviction. Keep queues, application locks and one-time tokens on
   the existing Redis instance with noeviction. Separate database numbers alone
   do not isolate memory or eviction. Keep existing sessions in place.
5. Tune PHP-FPM conservatively for measured memory use; enable slow request logs,
   worker recycling and sufficient OPcache space. Retain timestamp validation.
   Enable compression and immutable caching for Vite fingerprinted assets in
   Nginx while retaining production console proxy and TLS configuration.
6. Change both deployment entry points to public SSH host 5.202.19.100:2220.
   Deploy the exact committed origin/master revision using composer deploy.
   Validate Redis before publishing, reload PHP-FPM before health checks, and
   check each portal host. Preserve graceful Horizon job completion.
7. Verify portal isolation, mutation invalidation, expiry, cache failure recovery,
   cold/warm queries, service health, and queue processing. Report measured results.

## Applied configuration and checks

- Public deployment endpoint: `deploy@5.202.19.100:2220` (existing SSH key).
- Dedicated `redis-portal` systemd service on loopback port 6380, 256 MB LFU.
- PHP-FPM: 16 maximum children, 4 initial workers, 2–6 spare workers,
  recycling after 500 requests and a 5-second slow-request log.
- FPM OPcache: 256 MB, 32 MB interned strings and 30,000 cached files.
- Nginx: compression for text assets and immutable caching under `/build/assets/`.
- Existing application cache, session and queue connections remain unchanged.
  `PORTAL_CACHE_STORE=portal` selects Redis for the expensive presentation data.
- Graceful Horizon termination runs as `aviato`, its owning user, using the narrow
  sudoers rule in `ops/sudoers/aviato-deploy`; `deploy` cannot signal those PIDs.
- Baseline production finance summary: 13 SQL queries per call, approximately
  20–24 ms after the initial invocation (CLI service benchmark, not HTTP latency).

Reproduce the benchmark as the application user:

```sh
php ops/benchmark-portal.php /var/www/html/aviato/current --uncached
php ops/benchmark-portal.php /var/www/html/aviato/current
php artisan performance:check-cache
```

Service templates are under `ops/redis`, `ops/php` and `ops/nginx`. The root-run
`ops/apply-portal-performance` script accepts a staging directory with `redis/`,
`php/`, `performance.conf` and `build-assets.conf`. It backs up configuration,
checks syntax and health, and preserves existing Nginx console proxy settings.
The initial production backup is `/var/backups/aviato-performance/20261015T142515Z`.

Admin, customer and network invalidation scopes are separate, so ingesting network
buckets does not evict finance or monthly estimate snapshots. Scopes intentionally
invalidate broadly within each group; refine them per project only if measured
invalidation frequency limits cache effectiveness. Model updates bypassing Eloquent
events rely on the short TTL. Displayed unbilled accrual can lag by 30 seconds;
actual billing calculations continue to run uncached.

## Recovery

Back up active service configuration and the shared environment before changes.
The portal cache can be disabled with PORTAL_CACHE_ENABLED=false followed by
configuration recaching and worker reload. Roll back application code using the
previous Deployer release; restore service configuration separately after syntax
checks. Never flush the queue Redis instance as a deployment cache-clearing step.
Changing CACHE_STORE does not migrate existing database cache entries: allow
existing one-time impersonation tokens to expire before switching stores, and
avoid overlapping scheduled billing/network jobs during lock-store changes.

## Reference documentation

- https://laravel.com/framework/docs/cache/config
- https://redis.io/docs/latest/develop/reference/eviction/
- https://www.php.net/manual/en/install.fpm.configuration.php
- https://www.php.net/manual/en/opcache.configuration.php
