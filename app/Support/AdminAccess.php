<?php

namespace App\Support;

use App\Enums\AdminAbility;
use App\Enums\AdminRole;
use App\Models\User;

class AdminAccess
{
    /** @return list<AdminAbility> */
    public static function abilities(AdminRole $role): array
    {
        if ($role === AdminRole::Admin) {
            return AdminAbility::cases();
        }

        return match ($role) {
            AdminRole::Accountant => [
                AdminAbility::BillingRead, AdminAbility::CustomersRead,
                AdminAbility::ProjectsRead, AdminAbility::ResellersRead,
            ],
            AdminRole::Support => [
                AdminAbility::TicketsManage, AdminAbility::IncidentsManage,
                AdminAbility::CustomersRead, AdminAbility::ProjectsRead,
                AdminAbility::InfrastructureRead,
            ],
            AdminRole::Infrastructure => [
                AdminAbility::InfrastructureRead, AdminAbility::InfrastructureManage,
                AdminAbility::NetworkManage, AdminAbility::CustomersRead,
                AdminAbility::ProjectsRead,
            ],
            AdminRole::Admin => AdminAbility::cases(),
        };
    }

    public static function allows(User $user, AdminAbility|string $ability): bool
    {
        $ability = is_string($ability) ? AdminAbility::tryFrom($ability) : $ability;

        return $user->is_active && $ability !== null && in_array($ability, self::abilities($user->role), true);
    }

    public static function landingRoute(User $user): string
    {
        return match ($user->role) {
            AdminRole::Admin => 'admin.dashboard',
            AdminRole::Accountant => 'admin.billing.overview',
            AdminRole::Support => 'admin.tickets.index',
            AdminRole::Infrastructure => 'admin.virtual-machines.index',
        };
    }
}
