# VM requests and recovery

Power requests (start, restart, graceful shutdown, and force power off) have separate persistent operation records. Resource upgrades retain their existing order and checkpoints and also appear in the same operation history. A recovery force power off links to the previous failed restart or shutdown.

Customer VM pages and admin VM pages show recent requests separately from the VM's power state. Customer list cards show the active request. Pages refresh while a request is active. Force power off requires explicit acknowledgement that unsaved data can be lost; it does not automatically power the server on afterward.

## Execution and safety

- Power requests run on the existing `provisioning` queue. The existing scheduler dispatches reconciliation every minute. A web request does not wait for a server to restart.
- A unique nullable active VM key and a locked VM transaction prevent two tracked actions from being accepted concurrently. Upgrade, power, and deletion requests respect active operations. Existing lifecycle restrictions remain in place.
- Submission is marked before contacting the provider. An interrupted or timed-out submission is never sent again automatically. A provider task ID is retained as soon as it is available.
- A completed task is verified against the resulting power state. A running VM alone does not prove that a restart completed. Proxmox warning results still require a matching state and no configuration lock.
- Graceful shutdown does not fall back to force power off. A stalled shutdown or restart remains visible; force power off becomes available after the previous task has ended unsuccessfully. Active or uncertain operations cannot be bypassed through the force-off endpoint.
- Provider errors and task identifiers stay out of customer copy. Support can inspect the operation record and application logs.
- Active operations do not pause billing. Usage is accrued before a confirmed power-state change, preserving the previous billing behavior.

## Deployment

Run the additive migration with the normal deployment recipe, rebuild Blade views, and restart Horizon and the scheduler through the existing deploy hooks. No new queue or environment setting is required. Existing completed activity is preserved; old activity is not rewritten into operation records.

## Unknown results

An operation in `checking` remains exclusive until its outcome is verified. Support should inspect its VM, saved node/VMID, provider task ID and provider task log. If submission was interrupted before a task ID was saved, identify the corresponding provider task before resolving the operation. Do not clear the active VM key simply because a request is old, and do not repeat a disk or power command to determine whether it succeeded. A provider outage can leave requests under review until access is restored.

Imported LXC power controls retain their existing unsupported state. Password reset, rebuild, backup, provisioning, and deletion retain their existing dedicated progress/activity flows; this change unifies power requests and resource upgrades.
