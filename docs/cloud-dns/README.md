# Cloud DNS operations

## Implementation and deployment state

- NS1: `ns1.aviato.ir`, `5.202.19.14`, PowerDNS Authoritative 5.0.2 primary.
- NS2: `ns2.aviato.ir`, `5.202.19.15`, PowerDNS Authoritative 5.0.2 secondary.
- Each has a separate SQLite backend, automatic service startup, TSIG-authenticated transfers, and nightly local backups.
- Public UDP/TCP 53 is open. Management is HTTPS on private TCP 8443 (`172.19.19.12` / `172.19.19.13`), behind Nginx and UFW allowlists. PowerDNS's HTTP API listens only on loopback 8081.
- Management listens only on private IPs and allows production `172.19.19.6`. Public API listeners and their firewall rules have been removed.
- No DNSSEC signing or customer public API in this release. No CDN, proxy, WAF, or anycast functionality.
- `aviato.ir` stays on Cloudflare. Only DNS-only A records for ns1/ns2 are needed there.
- `ammir.ir` is the requested pilot; current DNS is Cloudflare. Production customer `09135512366` has default project UUID `292696c1-de04-4dae-bca8-c1cdab31af60` (پروژه اصلی آویاتو).
- Application code and migration are part of this release. Use the normal Deployer release process described below; do not copy changes onto the active release.
- Production `.env` has the new DNS connection settings and API credentials, with its previous contents backed up as `shared/.env.before-cloud-dns`. Its existing cached configuration is unchanged until normal deployment.

### Private network

The earlier public hairpin-NAT problem is bypassed with a dedicated private NIC on each DNS VM. Existing public `net0` devices are unchanged (`vmbr1`, VLAN 200). New `net1` devices use `vmbr0`, with Proxmox `ipfilter-net1` restricted to each assigned private IP:

| Server | VMID | Private IP | Private NIC MAC |
| --- | --- | --- | --- |
| NS1 | 164 | 172.19.19.12/24 | BC:24:11:6A:EC:C6 |
| NS2 | 169 | 172.19.19.13/24 | BC:24:11:DB:05:B6 |

Both VMs currently reside on Proxmox node `IR-KR-S1-SRV1`. Move NS2 to another physical host/failure domain for host redundancy.

Guest interface `eth1` is configured persistently in `/etc/netplan/60-cloud-dns-private.yaml`. The private gateway is `172.19.19.1`; source-based routing table 119 uses it for traffic sourced from the private IP. The public default route remains `5.202.19.97`. Original Netplan configuration was saved under `/root/cloud-dns-network-backup/`.

Production uses `172.19.19.6`. Its private HTTPS access to both APIs has been verified with the pinned management CA. PowerDNS listens for DNS on public and private addresses. Primary NOTIFY is restricted to the private secondary address; `ALSO-NOTIFY` supplies that address. Secondary zone masters are reconciled to the configured private primary address.

Certificates include the corresponding public and private IP SANs plus `ns1.aviato.ir` / `ns2.aviato.ir`. Management remains behind TLS with API keys, even on the private network.

## Deployment

Use the repository's normal reviewed Git/Deployer deployment process. Do not overlay files onto the active production release.

1. Verify both APIs from production over the private network.
2. Deploy the application through the normal release process, which runs migrations, builds frontend assets, caches configuration, and restarts Horizon/scheduler.
3. The migration adds project member `dns_read`/`dns_manage`, zones, RRsets, changes, and inventory snapshots. DNS table names intentionally do not overlap PowerDNS's own database tables.
4. Verify customer `/cloud-dns` and admin `/cloud-dns` on their respective portal hosts.
5. Verify Horizon handles the default queue. Existing default workers process `SyncDnsZone` and `CheckDnsDelegation`. The scheduler enqueues reconciliation every five minutes and captures daily inventory at 23:50 application time.
6. Prepare the pilot only once deployment is live, so the 48-hour TXT challenge does not expire before it can be used:

```bash
php artisan cloud-dns:add-domain 09135512366 ammir.ir --project=292696c1-de04-4dae-bca8-c1cdab31af60
```

7. Add the printed TXT record in Cloudflare's **ammir.ir** zone. Export Cloudflare's complete BIND zone and import through Aviato. Check web, mail, SPF, DKIM, DMARC, CAA, and verification records manually. Public DNS lookups cannot discover every existing record, so do not use guessed scans as a replacement for an export.
8. Verify ownership in Aviato; wait for `ready`, matching NS1/NS2 serials and equal desired/published revisions. Test both servers directly with `dig @5.202.19.14 ammir.ir SOA` and equivalent queries to NS2.
9. If ammir.ir has DNSSEC, remove the previous DS through NIC.ir and wait for the previous DS TTL to expire before migration. This release does not issue replacement DS records.
10. At NIC.ir, replace **ammir.ir's** nameservers with `ns1.aviato.ir` and `ns2.aviato.ir`, using `.14` and `.15` respectively where IPs are required. Keep Cloudflare serving the old zone while delegation caches expire. Do not change aviato.ir's Cloudflare delegation.

## Access and ownership

Zones belong to projects. Project owners and admins manage DNS. Other members require explicit `dns_read` or `dns_manage`; VM scope does not grant DNS access. Flags can be changed through customer or admin project membership forms.

Admin permissions `dns.read` and `dns.manage` control access to DNS pages and mutations. Full admins and infrastructure admins have DNS access; custom admin roles can receive these abilities explicitly. All admin mutations also use the existing admin audit middleware.

A per-domain unique TXT challenge must be visible in the existing DNS provider before provisioning. Delegation to the shared Aviato NS names is not ownership proof. Unverified reservations expire after 48 hours and can be reclaimed. Verified claims are retained through deletion until both nameservers confirm removal. Reassignment of an already verified domain requires support review; there is no automatic takeover path.

## Gradual rollout

Admin Cloud DNS includes a rollout panel controlled by `dns.rollout.manage` together with `dns.read`. Full administrators have it automatically; infrastructure/record-management roles do not receive rollout management implicitly. Custom admin roles can be granted the ability separately.

Modes are `selected` (pilot allowlist), `all`, and `disabled` (pause customer portal access). Missing or invalid configuration defaults to `selected` with an empty list. Add/remove customers by phone or email. Mode changes preserve the allowlist for later reuse. Settings are stored atomically in the existing `app_settings` row `cloud-dns.rollout`; no new migration is needed. Authorization reads this setting directly so revocations apply on the next request.

In selected mode, both the authenticated customer and the active project's owner must be allowlisted. Existing project membership and DNS read/manage permissions still apply. This prevents access through invitations to a non-pilot project or invitations to non-pilot members. The navigation is hidden for ineligible customers and all customer Cloud DNS actions, including direct URLs, exports and aliases, return 404.

The rollout flag gates customer portal access only. It does not delete zones, stop authoritative DNS answers, stop reconciliation, disable admin operations, or replace the technical `CLOUD_DNS_ENABLED` switch. Administrative/CLI provisioning remains available to trusted operators. Keep the technical switch enabled when pausing a rollout so existing zones continue syncing.

## DNS changes, retries and deletion

Aviato stores desired RRsets. Every mutation locks the zone row, increments a revision and records actor and before/after data. Verified zones enqueue idempotent synchronization. The worker locks each zone, replaces only changed RRsets on primary, configures secondary TSIG, and triggers transfers. It marks `ready` only after both serials match and only if the revision is still current.

API writes use verified HTTPS with the deployment's CA bundle. Provider errors are not exposed to customers. Jobs retry with backoff and reconciliation revisits zones after transient failures. The UI distinguishes ownership, parent delegation, and publication. A queued change is not shown as already published.

Deleting a zone removes secondary first, then primary. The domain remains reserved while deletion is incomplete. Tombstones and change history remain, with the original domain in the audit snapshot. Project/customer deletion is blocked while non-deleted DNS zones exist. Suspension similarly removes both served copies but retains desired records and ownership for resume. Resolver caches may continue returning old records until TTL expiry.

Apex NS and SOA are managed by Aviato. SOA uses `ns1.aviato.ir`, hostmaster contact `hostmaster.aviato.ir`, refresh 3600, retry 600, expire 1209600, and negative TTL 300. NS2 cannot serve indefinitely during a primary outage: SOA expiry is 14 days. Changes require primary availability or a controlled promotion.

## Record import and export

Supported: A, AAAA, CNAME, MX, TXT, CAA, SRV, and delegated NS. Wildcards and multi-value RRsets are supported. TTL is 60–86400 seconds. Apex CNAME is prohibited, and CNAME cannot coexist with other data at the same owner.

The BIND import parser accepts `$ORIGIN` for the selected zone, `$TTL`, inherited owner names, relative targets, quoted/multiline TXT, parentheses and comments. SOA and apex NS are ignored in favor of Aviato-managed records. Unsupported directives/types fail the whole import. Import replaces matching name/type RRsets and preserves other records. Cloudflare proxy settings are not DNS records and are not imported.

Exports contain the desired configuration, not a claim that changes have already reached both nodes. DNSSEC, HTTPS/SVCB, ALIAS flattening, and arbitrary BIND includes are not supported in this release.

## Plans and future billing

Each zone has a plan key, initially `free`. Quotas come from `config/cloud-dns.php` and environment limits (25 zones per project, 500 record values per zone by default). `dns_usage_snapshots` captures daily zone/record inventory by project/plan. No money is charged and wallet balances do not gate DNS. Future billing must define proration/metering and retention before using inventory snapshots as chargeable usage. Query-volume billing requires separate DNS-side telemetry; it is not implemented.

## Secrets and certificates

Keys and certificates are outside Git. Setup credentials and CA private key are in the operator's protected `~/.config/aviato-cloud-dns/`. Production has only the CA certificate at `shared/storage/app/private/cloud-dns/ca.crt` and API keys in `.env`; the CA private key is not installed there. Server credentials are root/pdns-readable configuration and root-only Nginx key files.

The management CA lasts 10 years; server certificates last 825 days from installation. Renew before expiry, preserve SANs for the configured public addresses/hostnames, and reload Nginx. Monitor expiry as part of operations. Replacing URLs with private-IP URLs later also requires certificates with matching SANs and updated firewall/Nginx allowlists. If the primary's transfer source changes, update `CLOUD_DNS_PRIMARY_IP` and secondary `allow-notify-from`.

SSH key access was already available. The shared login password supplied during setup has not been changed; rotate it through your normal server access process.

## Backup and recovery

Each server runs `/usr/local/sbin/aviato-dns-backup` nightly at 02:17 UTC. SQLite's online backup API is used via `.backup`; the job also saves PowerDNS configuration. Backups are root-only under `/var/backups/powerdns/` with 14-day local retention. Local copies are not a substitute for automated offsite backups.

To pull encrypted-transport copies to a protected backup destination:

```bash
ops/cloud-dns/pull-backups /protected/encrypted-backup-directory
```

Archives contain API and TSIG credentials. Protect and encrypt them. An initial copy was pulled to the operator's protected configuration directory. Schedule this script from an always-on management/backup host after backup-host connectivity and SSH keys are established; it is not scheduled on this workstation.

Restore the newest known-good database to an isolated temporary path, run SQLite `PRAGMA integrity_check`, and inspect the zone/TSIG metadata before using it. On the affected live node, stop PowerDNS, preserve the failed database/config, install the restored database with `pdns:pdns` ownership and mode 640, restore required config, start PowerDNS, then check SOA and records over UDP/TCP. Only one primary should accept writes. Take backups after changing server roles and verify transfer permissions before resuming jobs.

For primary failure, leave secondary answering existing zones while investigating. A promotion needs a planned single-writer switch, updated zone kinds, API role configuration, TSIG transfer direction, and SOA serial continuity; it is intentionally not automatic.

## Validation

- Automated feature tests cover project isolation, read/manage permissions, admin restrictions, unique TXT verification, RRset conflicts, validation, import/export, quotas, expired reservations, deletion retries, suspension, and idempotent synchronization with serial checks.
- Live temporary `.invalid` probes verified direct UDP/TCP answers, authenticated transfers, blocked unauthenticated AXFR, TLS verification, and full Laravel create/sync/delete across both nodes. Probe zones were removed.
- Desktop/mobile previews were rendered with production assets; no page JavaScript errors or horizontal overflow were observed, and record search was exercised.
- `npm run build` and Blade compilation passed. The existing large-JavaScript-chunk warning remains.
- Production private API reachability and authenticated private transfers are deployment prerequisites and were verified during setup.

### Record management and imports

Verified domains with active delegation show records first, with connection details collapsed below the table. The shared admin/customer editor provides type-specific fields, owner defaults, examples, and TTL defaults. `@` is valid for apex A, AAAA, MX, TXT, and CAA records; CNAME and NS require a non-apex owner.

Portal imports merge valid values into existing record sets, ignore canonical duplicate values, and preserve the TTL of existing sets. Invalid record values, unsupported types, non-apex SOA, CNAME conflicts, and quota overflow are reported without discarding other valid records. SOA and apex NS remain managed by Aviato. Unsafe file-level structure (such as an inconsistent ORIGIN or unclosed parentheses/quotes) still rejects the file before any writes. Imports containing only duplicates or rejected records do not increment the revision or queue synchronization.
