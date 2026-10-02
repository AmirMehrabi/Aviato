# Admin permissions

In **Admin users → Create/Edit → Access type**, choose **Custom access** (`دسترسی سفارشی`) and select permissions grouped by module. You can copy the Accountant, Support, or Infrastructure preset, then adjust individual checkboxes. Saved custom selections are independent of future preset changes. Selecting a fixed role instead clears its custom permissions.

Action permissions automatically include their required view permissions. Selecting a VM action, for example, adds VM view access. Empty custom access grants only the personal profile and shared services that filter their results by permission. Login selects the first accessible module, falling back to the profile.

Only full administrators can manage panel users or assign access. The last active full administrator and self-demotion protections remain in place. Role, status, and permission changes revoke the target user's sessions and are recorded in the admin audit log.

## Enforcement

- `AdminAbility` defines the permission catalog, Persian labels, and module groups.
- `AdminAccess` defines role presets, prerequisite permissions, route requirements, and landing pages. Unmapped routes deny access to restricted users.
- `AuthorizeAdminRoute` protects both the admin web portal and admin Proxmox API. Sensitive VM and customer edits also check their specific permissions inside the update endpoints. Changing an existing customer password requires `customers.credentials`, and deleting a customer requires `customers.delete`.
- Blade `@adminRoute('admin.route.name')` and `@adminAbility('ability.name')` conditions use the same access rules for navigation, links, forms, and data sections.
- Dashboard issues are filtered before counts, pagination, and dismissals. Financial summaries are not queried without `billing.read`. Customer, workspace, VM, Proxmox, and network pages hide financial sections and amounts without that permission.
- Global search queries only accessible modules. Notification feeds, unread counts, and read actions filter restricted records; ticket email/SMS recipients must retain ticket access. Impersonation handoffs recheck permissions.
- Restricted audit viewers see only records for routes they can access, preventing financial audit records from bypassing financial permissions.

`billing.read` grants financial record visibility. Exports, payment accounting actions, and wallet changes additionally require `billing.export`, `billing.manage`, and `wallet.manage`. Reseller records include financial data and therefore also require `billing.read`.

VM view, editing/provisioning, power, console, transfer/node movement, and deletion are separate permissions. Proxmox, Hetzner, infrastructure locations, cloud images, IP pools, network accounting, resource pricing, and bundles have separate module permissions.

When adding an admin module or sensitive action, add its ability and route requirements in `AdminAccess`, guard its UI, and verify indirect exposure through related views, dashboard, search, notifications, and audit records. `AdminCustomPermissionsTest` checks that every protected module route has a permission mapping and rejects unprivileged users.

## Migration and validation

Run `php artisan migrate` to add the nullable `users.permissions` JSON column. Existing users keep their fixed roles. `php artisan test` includes coverage for custom account creation/editing, permission revocation, privilege escalation, financial visibility, restricted routes, search, notifications, and login destinations.
