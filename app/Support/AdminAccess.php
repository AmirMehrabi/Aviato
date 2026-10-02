<?php

namespace App\Support;

use App\Enums\AdminAbility;
use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class AdminAccess
{
    /** @return list<AdminAbility> */
    public static function abilities(AdminRole $role): array
    {
        return match ($role) {
            AdminRole::Admin => AdminAbility::cases(),
            AdminRole::Custom => [],
            AdminRole::Accountant => [
                AdminAbility::BillingRead, AdminAbility::BillingExport, AdminAbility::CustomersRead,
                AdminAbility::ProjectsRead, AdminAbility::ResellersRead,
            ],
            AdminRole::Support => [
                AdminAbility::TicketsRead, AdminAbility::TicketsManage, AdminAbility::SupportManage,
                AdminAbility::IncidentsRead, AdminAbility::IncidentsManage,
                AdminAbility::CustomersRead, AdminAbility::ProjectsRead, AdminAbility::VmRead,
            ],
            AdminRole::Infrastructure => [
                AdminAbility::InfrastructureRead, AdminAbility::InfrastructureManage,
                AdminAbility::NetworkRead, AdminAbility::NetworkManage,
                AdminAbility::HetznerRead, AdminAbility::HetznerManage,
                AdminAbility::LocationsRead, AdminAbility::LocationsManage,
                AdminAbility::ImagesRead, AdminAbility::ImagesManage,
                AdminAbility::IpPoolsRead, AdminAbility::IpPoolsManage,
                AdminAbility::VmRead, AdminAbility::VmManage, AdminAbility::VmPower,
                AdminAbility::VmConsole, AdminAbility::VmTransfer, AdminAbility::VmDelete,
                AdminAbility::CustomersRead, AdminAbility::ProjectsRead,
            ],
        };
    }

    /** @return list<string> */
    public static function permissions(User $user): array
    {
        return $user->role === AdminRole::Custom
            ? array_values(array_intersect($user->permissions ?? [], array_column(AdminAbility::cases(), 'value')))
            : array_column(self::abilities($user->role), 'value');
    }

    public static function allows(User $user, AdminAbility|string $ability): bool
    {
        $ability = is_string($ability) ? AdminAbility::tryFrom($ability) : $ability;

        // Account management includes assigning access and is reserved for full administrators.
        if ($ability === AdminAbility::UsersManage && $user->role !== AdminRole::Admin) {
            return false;
        }

        return $user->is_active && $ability !== null && in_array($ability->value, self::permissions($user), true);
    }

    public static function allowsRoute(User $user, string $name, string $method = 'GET'): bool
    {
        if (! $user->is_active) {
            return false;
        }
        if ($user->role === AdminRole::Admin) {
            return true;
        }
        if (str_starts_with($name, 'admin.profile.') || str_starts_with($name, 'admin.table-preferences.')
            || str_starts_with($name, 'admin.notifications.') || $name === 'admin.search') {
            return true;
        }
        // Preserve existing role landing redirects; custom dashboards require explicit access.
        if ($name === 'admin.dashboard' && $user->role !== AdminRole::Custom) {
            return true;
        }

        $required = self::routeAbilities($name, $method);

        return $required !== [] && collect($required)->every(fn (AdminAbility $ability): bool => self::allows($user, $ability));
    }

    /** @return list<AdminAbility> */
    public static function routeAbilities(string $name, string $method = 'GET'): array
    {
        $action = substr($name, strrpos($name, '.') + 1);
        $read = in_array($method, ['GET', 'HEAD'], true) && ! in_array($action, ['create', 'edit'], true);
        if (str_starts_with($name, 'admin.dashboard')) {
            return [AdminAbility::Dashboard];
        }
        foreach ([
            'admin.users.' => AdminAbility::UsersManage,
            'admin.audit.' => AdminAbility::AuditView,
            'admin.settings.' => AdminAbility::SettingsManage,
            'admin.api-activity.' => AdminAbility::ApiActivityView,
            'admin.promotions.' => AdminAbility::PromotionsManage,
            'admin.promotion-users.' => AdminAbility::PromotionsManage,
            'admin.billing.rates.' => AdminAbility::PricingManage,
            'admin.billing.bundles.' => AdminAbility::BundlesManage,
            'admin.support-teams.' => AdminAbility::SupportManage,
            'admin.ticket-categories.' => AdminAbility::SupportManage,
        ] as $prefix => $ability) {
            if (str_starts_with($name, $prefix)) {
                return [$ability];
            }
        }
        if (str_starts_with($name, 'admin.billing.network.')) {
            return [AdminAbility::NetworkRead, ...($read ? [] : [AdminAbility::NetworkManage])];
        }
        if (str_starts_with($name, 'admin.billing.')) {
            return [AdminAbility::BillingRead, ...($name === 'admin.billing.exports'
                ? [AdminAbility::BillingExport] : ($read ? [] : [AdminAbility::BillingManage]))];
        }
        if (str_starts_with($name, 'admin.customers.')) {
            $special = match ($name) {
                'admin.customers.wallet-transactions.store', 'admin.customers.wallet-lock.update',
                'admin.customers.auto-suspension.update' => [AdminAbility::BillingRead, AdminAbility::WalletManage],
                'admin.customers.destroy' => [AdminAbility::CustomersDelete],
                'admin.customers.impersonate' => [AdminAbility::CustomersImpersonate],
                'admin.customers.suspend', 'admin.customers.activate' => [AdminAbility::CustomersSuspend],
                default => $read ? [] : [AdminAbility::CustomersManage],
            };

            return [AdminAbility::CustomersRead, ...$special];
        }
        if (str_starts_with($name, 'admin.projects.')) {
            return [AdminAbility::ProjectsRead, ...match (true) {
                $name === 'admin.projects.proforma' => [AdminAbility::BillingRead],
                str_starts_with($name, 'admin.projects.wallet-alerts.') => [AdminAbility::BillingRead, AdminAbility::WalletManage],
                default => $read ? [] : [AdminAbility::ProjectsManage],
            }];
        }
        if (str_starts_with($name, 'admin.virtual-machines.')) {
            return [AdminAbility::VmRead, ...match (true) {
                $action === 'options' => [AdminAbility::VmManage],
                str_contains($name, '.console.') => [AdminAbility::VmConsole],
                str_contains($name, '.transfer'), $action === 'move-node' => [AdminAbility::VmTransfer],
                in_array($action, ['start', 'stop'], true) => [AdminAbility::VmPower],
                $action === 'destroy' => [AdminAbility::VmDelete],
                default => $read ? [] : [AdminAbility::VmManage],
            }];
        }
        if (str_starts_with($name, 'admin.unprovisioned-virtual-machines.')) {
            return [AdminAbility::InfrastructureRead, ...($read ? [] : [AdminAbility::VmRead, AdminAbility::VmManage])];
        }
        if (str_starts_with($name, 'admin.ip-pools.')) {
            return [AdminAbility::IpPoolsRead, ...($read ? [] : [AdminAbility::IpPoolsManage])];
        }
        foreach ([
            'admin.hetzner-accounts.' => [AdminAbility::HetznerRead, AdminAbility::HetznerManage],
            'admin.infrastructure-locations.' => [AdminAbility::LocationsRead, AdminAbility::LocationsManage],
            'admin.cloud-images.' => [AdminAbility::ImagesRead, AdminAbility::ImagesManage],
        ] as $prefix => [$view, $manage]) {
            if (str_starts_with($name, $prefix)) {
                return [$view, ...($read ? [] : [$manage])];
            }
        }
        foreach (['admin.proxmox-servers.', 'api.admin.proxmox-servers.'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return [AdminAbility::InfrastructureRead, ...($read ? [] : [AdminAbility::InfrastructureManage]),
                    ...(str_contains($name, '.stale-virtual-machines.') ? [AdminAbility::VmDelete] : [])];
            }
        }
        if (str_starts_with($name, 'admin.tickets.')) {
            return [AdminAbility::TicketsRead, ...($read || $action === 'seen' ? [] : [AdminAbility::TicketsManage])];
        }
        if (str_starts_with($name, 'admin.incidents.')) {
            return [AdminAbility::IncidentsRead, ...($read ? [] : [AdminAbility::IncidentsManage])];
        }
        if (str_starts_with($name, 'admin.resellers.')) {
            return [AdminAbility::ResellersRead, AdminAbility::BillingRead, ...($read ? [] : [AdminAbility::ResellersManage])];
        }

        return [];
    }

    /** @param list<string> $permissions @return list<string> */
    public static function normalizePermissions(array $permissions): array
    {
        $dependencies = [
            'billing.export' => ['billing.read'], 'billing.manage' => ['billing.read'],
            'wallet.manage' => ['billing.read'],
            'customers.manage' => ['customers.read'],
            'customers.delete' => ['customers.read'],
            'customers.credentials' => ['customers.read', 'customers.manage'], 'customers.suspend' => ['customers.read'],
            'customers.impersonate' => ['customers.read'],
            'projects.manage' => ['projects.read'],
            'tickets.manage' => ['tickets.read'], 'support.manage' => ['tickets.read'],
            'incidents.manage' => ['incidents.read'],
            'infrastructure.manage' => ['infrastructure.read'],
            'network.manage' => ['network.read'],
            'hetzner.manage' => ['hetzner.read'],
            'locations.manage' => ['locations.read'],
            'cloud-images.manage' => ['cloud-images.read'],
            'ip-pools.manage' => ['ip-pools.read'],
            'resellers.read' => ['billing.read'], 'resellers.manage' => ['resellers.read', 'billing.read'],
        ];
        foreach (['manage', 'power', 'console', 'transfer', 'delete'] as $action) {
            $dependencies['virtual-machines.'.$action] = ['virtual-machines.read'];
        }
        foreach ($permissions as $permission) {
            $permissions = [...$permissions, ...($dependencies[$permission] ?? [])];
        }
        sort($permissions);

        return array_values(array_unique($permissions));
    }

    public static function canVisit(User $user, string $name): bool
    {
        $route = Route::getRoutes()->getByName($name);

        return $route && self::allowsRoute($user, $name, $route->methods()[0]);
    }

    /** @param array<string|int, mixed> $data @return array<string|int, mixed> */
    public static function redactFinancialData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match('/amount|balance|price|cost|wallet|invoice|payment|commission|currency|financial|billed|estimated_monthly_delta/i', (string) $key)) {
                $data[$key] = '[RESTRICTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::redactFinancialData($value);
            }
        }

        return $data;
    }

    public static function landingRoute(User $user): string
    {
        if ($user->role !== AdminRole::Custom) {
            return match ($user->role) {
                AdminRole::Admin => 'admin.dashboard',
                AdminRole::Accountant => 'admin.billing.overview',
                AdminRole::Support => 'admin.tickets.index',
                AdminRole::Infrastructure => 'admin.virtual-machines.index',
            };
        }
        foreach (['admin.dashboard', 'admin.tickets.index', 'admin.incidents.index', 'admin.customers.index',
            'admin.projects.index', 'admin.virtual-machines.index', 'admin.billing.overview', 'admin.proxmox-servers.index',
            'admin.ip-pools.index', 'admin.billing.network.index', 'admin.hetzner-accounts.index', 'admin.infrastructure-locations.index', 'admin.cloud-images.index', 'admin.resellers.index', 'admin.promotions.index', 'admin.billing.rates.index',
            'admin.billing.bundles.index', 'admin.settings.edit', 'admin.audit.index', 'admin.api-activity.index'] as $route) {
            if (self::allowsRoute($user, $route)) {
                return $route;
            }
        }

        return 'admin.profile.edit';
    }
}
