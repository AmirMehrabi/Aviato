# Per-VM hardware and pricing

The admin VM detail page includes **سخت‌افزار و قیمت‌گذاری**. Changes follow selection → review → confirmation → operation progress. Every confirmation requires a reason and uses an encrypted, administrator-bound quote valid for ten minutes. A changed configuration, pricing policy, inherited rate or Proxmox digest invalidates the quote. Replaying a confirmed quote does not create another operation.

## Deployment

Apply `2026_11_08_120000_add_vm_hardware_and_pricing.php` through the normal deployment migration step, build assets, and restart queue workers with the application's normal deployment procedure. The migration is additive: existing machines inherit their existing price and no initial hardware acceptance is performed.

The existing `upgrades` queue executes hardware changes. The existing `provisioning` queue handles observations. The Laravel scheduler runs `vm-hardware:observe` every ten minutes, queuing up to 100 eligible machines per pass in least-recently-attempted order. Increase `--limit` for larger installations. Failed checks record their attempted time without discarding the last successful observation; active operations and LXC guests are excluded. Observations never accept configuration or alter pricing. The VM list can filter machines with observed hardware differences.

Proxmox token permissions must cover QEMU effective config and pending config, VM status, node status, storage status, CPU/memory configuration, disk resize, and graceful shutdown/start as appropriate. Verify role assignments against the deployed Proxmox version before rollout. No live provider writes are needed to run tests.

## Hardware

- Scope: connected Proxmox QEMU machines. Hetzner retains provider bundle upgrades; LXC hardware mutation is excluded.
- CPU totals account for sockets. Managed changes preserve socket topology and reject configurations with an explicit `vcpus` limit; use Proxmox and then acceptance for these configurations.
- Observations preserve memory in MiB and disk sizes in bytes, including decimal M/G/T values. The existing accepted VM fields use whole GiB, so fractional-GiB configurations are displayed but cannot be accepted through this release. No silent rounding is performed during acceptance.
- Effective config is read separately from pending reboot changes. Pending changes, provider locks, unknown power state, unexpected guest identity and a changed accepted primary volume block acceptance/mutation.
- The primary device comes from the VM's desired state or image mapping. ISO, cloud-init and non-data devices are excluded. Other attached data disks are visible, but this release accepts and resizes only the primary disk; it does not import or remove extra-disk billing records automatically.
- Managed requests require accepted hardware to match the fresh remote hardware first. CPU/RAM can decrease subject to image minimums; primary disks can only grow. RAM/disk increases preflight free node/storage capacity. Capacity can change before execution; provider failures remain visible and are reconciled.
- Running machines use graceful shutdown. There is no automatic force-stop fallback. Initially stopped machines remain stopped, and depleted wallets prevent restart. Guest partition/filesystem expansion is separate from virtual disk capacity and must be verified by the operator.
- Primary disk reduction requires a separately planned backup/migration/rebuild procedure. It is not a resize action.
- Node migration/replacement is resolved using the existing node-move/infrastructure tools before accepting hardware; observations do not silently remap VM identity.

The existing unique active-VM operation constraint serializes hardware requests with other operations. Upgrade checkpoints and provider read-back support retries and partial outcomes. Unknown outcomes remain locked for reconciliation; a partial change is not automatically rolled back, and an enlarged disk is never shrunk. The old agreement remains effective until verified completion. A verified shutdown creates a separate stopped-rate billing boundary. Admins see the operation progress and provider failure reason on the VM page.

Additional disks created by the existing upgrade workflow now carry `serial=aviato-upgrade-{order_id}`. The selected slot is persisted before submission. Retries recognize that serial and size rather than attaching another disk or adopting an unrelated disk occupying the slot.

## Pricing and billing

- `inherit`: existing bundle/location/provider behavior, unchanged by hardware acceptance.
- `custom`: this VM's explicit running and stopped monthly-equivalent base prices, before tax. Zero is a valid free price. The stopped amount must not exceed the running amount. Removing an override requires reviewing the inherited replacement price.
- `resources`: accepted CPU/RAM/disk/IP multiplied by active resource rates; available for Proxmox. CPU/RAM are excluded from the stopped base rate.

The bundle remains a reference and continues to supply inherited network policy. Base overrides exclude separately charged backups, additional disks and network overage. Inputs name the configured accounting currency explicitly; IRR input fields explain the rial/toman conversion. Displayed totals use the existing currency formatter. This feature does not migrate the application's accounting currency or apply foreign exchange to old agreements.

Monthly-equivalent prices use 730 hours. Actual usage remains PAYG. Custom agreements survive hardware changes, normal metadata edits and ownership transfer. Customer bundle upgrades are disabled for custom-priced machines, with an explanation directing them to support; an administrator manages their resources and explicitly reviews any price-policy change. Rebuild fees use the effective VM base price.

Price changes and accepted external resources are prospective. Usage is accrued at the old rate before the local configuration changes in the same transaction. External historical change times are not guessed and old invoices are not rewritten. Issued invoices and settled accruals retain their existing immutability rules. Per-segment agreement, tax, resources, decimal rates and unrounded amounts preserve auditability. Daily rounding is allocated across segments, so repeatedly changing a price does not erase fractional usage or repeatedly round it up. Invoice lines distinguish pricing/resource/rate/tax revisions within a day.

Admin bundle/resource-rate mutations checkpoint old usage before saving the new rates. Bundle deletion is blocked while assigned to an active VM. Bundle editing waits for dependent upgrades to finish. Custom fixed prices do not follow catalogue edits. Direct database writes, external scripts and other catalogue writers must use the same boundary service; global tax/location/FX catalogue editing retains its existing workflows and is not converted into a historical rate catalogue here.

Upgrade affordability uses the billing owner's effective balance, including pending usage, and reserves the minimum coverage of other pending upgrades under an owner lock. Administrators may still prospectively correct a price or accept already changed external hardware without funding the wallet; existing wallet enforcement controls power afterward. Hardware-only administrator changes retain the current pricing policy and do not take an immediate upgrade fee.

Transfers accrue elapsed VM, backup and extra-disk usage to the old owner before changing ownership. Future usage is charged to the new owner. Custom pricing remains attached and is called out in the transfer UI; historical debt is not copied or charged twice.

## Access and auditing

Reading/refreshing observations requires VM read access. Accepting hardware requires VM management; managed hardware also requires power access. Price changes require pricing management. Kind-specific permissions are rechecked against the signed quote at confirmation. Price amounts are rendered only to administrators with billing read access. Configuration history records actor, reason, before/after snapshots, request identity and effective time in addition to the existing admin mutation audit.

## Verification

`tests/Feature/AdminVmConfigurationTest.php` covers review/confirmation, expiry/tampering, actor binding, replay, permissions, drift, effective versus pending config, custom/free pricing, billing cutoffs, rounding, invoices, catalogue changes, stopped-machine reductions, stale worker requests, storage capacity, disk identity/retry, transfers and metadata edits. Provider calls are stubbed. Existing provisioning, upgrades, billing and admin-action suites provide regression coverage.

Desktop and mobile layouts are visually checked using rendered fixture screens. The screens and automated tests do not mutate real virtual machines.
