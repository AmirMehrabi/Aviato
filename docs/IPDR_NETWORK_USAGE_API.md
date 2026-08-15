# IPDR → Aviato Network Usage API Contract

This document is the implementation specification for the IPDR project. Aviato owns pricing, allowances, wallet charges, refunds, invoices, and financial history. IPDR only owns collection, normalization, attribution, finalization, correction, and delivery of byte usage.

## Required behavior

- Expose versioned HTTPS JSON endpoints under `/api/v1`.
- Authenticate Aviato with a bearer token. Tokens must be rotatable and scoped to usage reads.
- Attribute usage to Aviato's immutable `virtual_machines.uuid`, never only an IP, VMID, hostname, or MAC.
- Maintain an `assignment_id` for each continuous VM/network identity assignment. Reusing an IP for another VM must create a new assignment ID.
- Produce fixed, non-overlapping, end-exclusive UTC buckets. One-hour buckets are preferred.
- Return integer bytes, not floating point KB/MB/GB values.
- Return ingress and egress separately, regardless of which direction Aviato currently charges.
- Normalize cumulative device counters into interval deltas inside IPDR. Never return negative bytes.
- Handle flow deduplication, counter wrap/reset, router reboot, collector restart, and out-of-order samples inside IPDR.
- Do not silently mutate published data. Corrections retain `bucket_id` and increment `revision`.
- The exact same `bucket_id` + `revision` must always have byte-for-byte equivalent semantic content.
- A bucket must never cross an `Asia/Tehran` calendar-month boundary. Split the bucket at that boundary when necessary.

## Endpoint 1: list usage buckets

```http
GET /api/v1/usage/buckets?cursor=<opaque>&limit=500
Authorization: Bearer <token>
Accept: application/json
```

For bounded recovery/backfill, also support:

```http
GET /api/v1/usage/buckets?from=2026-08-01T00:00:00Z&to=2026-09-01T00:00:00Z&limit=500
```

`cursor` is opaque. Results must be ordered deterministically by the change stream, not merely by interval time. The stream must include newly created buckets, corrections, and voids. A cursor resumes strictly after the last returned change.

Response:

```json
{
  "schema_version": 1,
  "items": [
    {
      "bucket_id": "01K2D8FGJ3XYMAB6RZJ7T6NTJD",
      "revision": 1,
      "status": "final",
      "vm_uuid": "882c29c8-6aac-4d80-90fc-959fd600ec96",
      "assignment_id": "01JZP7SGN9Q11W7F5AJTK8BS61",
      "interval_start": "2026-08-15T08:00:00Z",
      "interval_end": "2026-08-15T09:00:00Z",
      "ingress_bytes": 12884901888,
      "egress_bytes": 5368709120,
      "sample_count": 3587,
      "completeness": "complete",
      "calculation_version": "ipdr-2.3.1",
      "finalized_at": "2026-08-15T09:10:14Z",
      "updated_at": "2026-08-15T09:10:14Z"
    }
  ],
  "next_cursor": "eyJ...",
  "has_more": true,
  "generated_at": "2026-08-15T09:11:00Z"
}
```

### Required item fields

| Field | Type | Rules |
|---|---|---|
| `bucket_id` | string, max 128 | Globally stable and unique within this IPDR source |
| `revision` | integer | Starts at 1 and strictly increases for a correction |
| `status` | string | `provisional`, `final`, or `void` |
| `vm_uuid` | UUID | Exact Aviato VM UUID |
| `assignment_id` | string, max 128 | Stable only for one continuous assignment epoch |
| `interval_start` | RFC3339 timestamp | UTC recommended; inclusive |
| `interval_end` | RFC3339 timestamp | Exclusive; after start; maximum 24 hours |
| `ingress_bytes` | non-negative integer | Bytes observed entering the VM |
| `egress_bytes` | non-negative integer | Bytes observed leaving the VM |
| `completeness` | string | `complete`, `partial`, or `missing` |
| `calculation_version` | string | IPDR normalization algorithm/version |
| `finalized_at` | timestamp/null | Required in practice for `final` |
| `updated_at` | timestamp | When this revision entered the change stream |

A provisional or partial bucket is stored by Aviato but not charged. Once complete, publish a higher revision with `status=final` and `completeness=complete`. To retract usage, publish a higher revision with `status=void`; retain identifying fields and use zero byte counts.

When `has_more=true`, `next_cursor` must be non-null. IPDR must retain cursors long enough for ordinary outages. If a cursor expires, respond with the structured `INVALID_CURSOR` error below; Aviato will recover using a bounded `from`/`to` backfill.

## Endpoint 2: independent reconciliation summary

```http
GET /api/v1/usage/summaries?from=2026-08-01T00:00:00Z&to=2026-09-01T00:00:00Z
```

Optional filter: `vm_uuid=<uuid>`.

Response:

```json
{
  "schema_version": 1,
  "from": "2026-08-01T00:00:00Z",
  "to": "2026-09-01T00:00:00Z",
  "items": [
    {
      "vm_uuid": "882c29c8-6aac-4d80-90fc-959fd600ec96",
      "ingress_bytes": 1209462790553,
      "egress_bytes": 483785116221,
      "final_bucket_count": 744,
      "partial_bucket_count": 0,
      "missing_bucket_count": 0,
      "digest": "sha256:<digest-of-current-final-buckets>"
    }
  ],
  "generated_at": "2026-09-01T01:00:00Z"
}
```

Summary totals include only the current revision of non-void, complete, final buckets whose interval is fully contained in `[from,to)`. Document the digest canonicalization algorithm if `digest` is supplied.

## Assignment data IPDR must retain

For every metered assignment retain:

```json
{
  "assignment_id": "01JZP7SGN9Q11W7F5AJTK8BS61",
  "vm_uuid": "882c29c8-6aac-4d80-90fc-959fd600ec96",
  "ip_address": "203.0.113.42",
  "mac_address": "BC:24:11:AA:BB:CC",
  "provider": "proxmox",
  "provider_server_id": "pve-tehran-1",
  "provider_vm_id": "1042",
  "valid_from": "2026-08-15T08:12:31Z",
  "valid_until": null
}
```

IPDR must reject or quarantine observations outside the assignment validity interval. If IPDR does not yet receive assignments automatically, implement an import/mapping facility keyed by Aviato VM UUID before exposing billable data. Do not guess identity from a currently assigned IP.

## Error format

Use appropriate HTTP status codes and this body:

```json
{
  "error": {
    "code": "INVALID_CURSOR",
    "message": "The cursor has expired.",
    "request_id": "01K2D..."
  }
}
```

Expected codes include `UNAUTHENTICATED`, `FORBIDDEN`, `VALIDATION_FAILED`, `INVALID_CURSOR`, `RATE_LIMITED`, and `INTERNAL_ERROR`. Return `Retry-After` for 429/503 when possible.

## Delivery and correctness tests IPDR must pass

1. Replaying a page returns identical bucket/revision content.
2. A correction retains `bucket_id` and increments `revision`.
3. A void is delivered through an existing cursor stream.
4. IP reuse produces a new `assignment_id` and never moves old traffic to the new VM.
5. No final buckets overlap for one assignment.
6. Missing collection is reported as missing/partial, never silently converted to zero usage.
7. Counter reset/wrap produces valid non-negative interval deltas.
8. Pagination cannot skip or duplicate a change when writes occur concurrently.
9. Backfill returns the current revisions needed to reconstruct totals.
10. Summary totals exactly equal the current final bucket set.
11. Timestamps around the Tehran month boundary are split correctly.
12. Byte fields remain exact above 32-bit integer limits.

## Information to return to the Aviato implementer

After implementation, provide:

- Base URL for staging and production.
- Bearer-token provisioning/rotation procedure.
- Retention period for buckets, revisions, cursors, and backfills.
- Finalization delay/SLA.
- Rate limits and maximum page size.
- Exact ingress/egress observation point and whether internal/private traffic is counted.
- Description of deduplication, reset, and collector-failover behavior.
- Sample payloads for normal, zero traffic, partial, missing, correction, void, reset, and IP-reassignment cases.
- OpenAPI document or generated endpoint documentation.
- The `calculation_version` release/change policy.

Do not implement prices, free allowances, IRR/IRT conversion, wallets, invoices, or customer ownership in IPDR. Those remain exclusively in Aviato.


