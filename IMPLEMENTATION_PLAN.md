# Aviato S3 Storage MVP — Implementation Checklist

This checklist is the working implementation contract. Each item should be checked only after the code, focused tests, and the relevant customer-facing behavior are verified.

## Product and architecture decisions

- [x] Keep Laravel as the control plane, customer portal, billing system, and S3 gateway.
- [x] Use AWS Signature Version 4 for S3 data-plane authentication.
- [x] Start with a private local object volume behind an `ObjectStore` abstraction.
- [x] Allow customers to create buckets inside their projects.
- [x] Use path-style S3 endpoint addressing for the MVP.
- [x] Support core object operations plus multipart uploads.
- [x] Bill daily stored capacity and successful request operations.
- [x] Defer egress billing, replication, versioning, ACLs, lifecycle policies, notifications, object lock, and public buckets.
- [ ] Document the single-node durability limitation before enabling production sales.

## Phase 1 — Storage domain and customer control plane

### Database and models

- [x] Add `storage_buckets` with project ownership, unique bucket name, region, status, quota, usage counters, and timestamps.
- [x] Add `storage_access_keys` with access key ID, encrypted secret, project ownership, status, description, and last-used timestamp.
- [x] Add `storage_objects` with bucket, object key, size, ETag, content type, metadata, checksum, and physical path.
- [x] Add `storage_multipart_uploads` and `storage_multipart_parts`.
- [x] Add relationships to `Project`, `Customer`, and the storage models.
- [x] Add safe casts and hidden fields for secrets and internal paths.
- [x] Add indexes for project/bucket lookup, object prefix listing, active keys, and multipart cleanup.

### Management service and authorization

- [x] Create a project-scoped `StorageService` for bucket and credential lifecycle operations.
- [x] Enforce `ProjectAccessService` membership and management permissions.
- [ ] Define `storage:read`, `storage:manage`, and `storage:credentials` management abilities.
- [x] Generate S3-compatible access key IDs and cryptographically random secrets.
- [x] Show a secret only at creation time and never log it.
- [x] Reject bucket deletion while objects or active multipart uploads exist.
- [ ] Add configurable bucket, object-size, storage-quota, and multipart limits.

### Customer-facing portal

- [x] Add a dedicated customer storage page linked from the customer navigation.
- [x] Show active project, bucket count, storage usage, quota, and billing status.
- [x] Add a clear create-bucket form with S3 naming rules and inline validation.
- [x] Show bucket endpoint, region, object count, and usage.
- [x] Add credential creation with a prominent one-time-secret warning.
- [x] Add credential revocation with confirmation and visible status.
- [x] Use prominent shared success/error banners for bucket and credential actions.
- [x] Keep copy task-oriented and customer-facing; avoid internal terms such as “data plane”.

## Phase 2 — S3 protocol gateway

### Authentication and request pipeline

- [x] Add a dedicated S3 hostname/path configuration.
- [x] Parse path-style bucket and object keys without losing encoded characters.
- [x] Implement SigV4 header authentication.
- [x] Implement SigV4 presigned-query authentication.
- [ ] Validate request dates, signed headers, payload hashes, and replay-sensitive timestamps.
- [ ] Add constant-time signature comparisons.
- [x] Return standard S3 XML errors and request IDs.
- [x] Ensure S3 request logging redacts authorization, signatures, secrets, and object content.

### Core bucket and object operations

- [x] Implement bucket create, head, list, and delete.
- [x] Implement initial ListObjectsV2-compatible listing with prefix and max keys.
- [x] Implement object PUT with streaming writes and metadata persistence.
- [x] Implement object GET with streaming responses.
- [x] Implement object HEAD with standard metadata headers.
- [x] Implement object DELETE and idempotent missing-object behavior.
- [x] Implement basic ETag handling.
- [x] Implement range reads, conditional requests, continuation tokens, delimiters, and common prefixes.
- [ ] Implement server-side copy.

### Multipart operations

- [x] Initiate multipart uploads.
- [x] Upload individual parts to temporary private paths.
- [x] List uploaded parts.
- [x] Complete uploads in part-number order with deterministic final ETag behavior.
- [x] Abort uploads and delete temporary parts.
- [ ] Add scheduled cleanup for expired multipart sessions.

### Object backend

- [ ] Define an `ObjectStore` interface independent of Laravel controllers.
- [x] Implement the private local-volume adapter.
- [x] Use atomic finalization for completed objects.
- [x] Stream reads and writes instead of buffering objects in PHP memory.
- [x] Prevent path traversal and unsafe key-to-path conversion.
- [x] Keep the adapter replaceable by MinIO, RustFS, or Ceph later.

## Phase 3 — Usage billing and operations

- [x] Point `s3.aviato.ir` DNS to the Aviato gateway.
- [ ] Provision a valid TLS certificate for `s3.aviato.ir`.
- [ ] Install and reload the Nginx S3 vhost on production.
- [x] Configure the reverse proxy template to preserve the `Host`, path, query string, and request body for S3 clients.
- [ ] Add storage and request resource-rate types.
- [ ] Record successful request usage by project, bucket, operation, and service date.
- [ ] Calculate daily average stored bytes.
- [ ] Integrate accruals with existing daily wallet settlement.
- [ ] Include storage usage in monthly invoice generation.
- [ ] Add admin storage usage and failed-request visibility.
- [ ] Add retention/pruning for request and multipart operational records.
- [ ] Add health checks for the storage volume, free space, and write/read probes.
- [ ] Add alerts for quota exhaustion, low disk space, and cleanup failures.

## Phase 4 — API docs and SDK usability

- [ ] Add management API endpoints for buckets, usage, and access keys.
- [x] Add S3 endpoint and AWS CLI examples to `/api-docs`.
- [x] Add PHP AWS SDK examples.
- [x] Add JavaScript AWS SDK examples.
- [ ] Add Laravel filesystem configuration examples.
- [x] Document current limits, unsupported features, billing status, and errors.
- [x] Make the upload/download examples executable against the configured Aviato endpoint after replacing clearly marked credentials and bucket placeholders.

## Phase 5 — Verification and launch gates

- [ ] Add unit tests for bucket naming, key generation, path safety, ETags, limits, and billing calculations.
- [ ] Add feature tests for project isolation and management abilities.
- [ ] Add SigV4 valid/invalid signature tests.
- [ ] Add core object operation tests.
- [x] Add multipart lifecycle tests.
- [ ] Add AWS CLI smoke tests.
- [ ] Add AWS SDK for PHP smoke tests.
- [ ] Run focused PHPUnit tests, `php artisan view:cache`, PHP lint, frontend build, and `git diff --check`.
- [ ] Verify no secrets, object bytes, or authorization headers appear in logs.
- [ ] Confirm the single-node storage limitation is visible to customers and operations staff.

## Current implementation slice

- [x] Create storage domain migrations and models.
- [x] Add storage management service and customer management endpoints.
- [x] Add customer-facing storage page and navigation entry.
- [x] Add focused tests for bucket and access-key management.
