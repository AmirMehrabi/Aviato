<?php

namespace App\Http\Middleware;

use App\Enums\AdminRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeAdminRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');
        $name = (string) $request->route()?->getName();

        abort_unless($user && $this->allowed($user->role, $name, $request->method()), 403, 'Access denied.');

        return $next($request);
    }

    private function allowed(AdminRole $role, string $name, string $method): bool
    {
        if ($role === AdminRole::Admin) {
            return true;
        }

        if ($this->startsWith($name, [
            'admin.notifications.', 'admin.table-preferences.', 'admin.profile.',
            'admin.search', 'admin.dashboard',
        ])) {
            return true;
        }

        if ($this->startsWith($name, ['admin.users.', 'admin.audit.', 'admin.settings.', 'admin.api-activity.', 'admin.promotions.', 'admin.promotion-users.'])) {
            return false;
        }

        if ($this->startsWith($name, ['admin.billing.payments.', 'admin.billing.transactions.', 'admin.billing.invoices.', 'admin.billing.usage.', 'admin.billing.wallets.', 'admin.billing.overview', 'admin.billing.exports'])) {
            return $role === AdminRole::Accountant && in_array($method, ['GET', 'HEAD'], true);
        }

        if ($this->startsWith($name, ['admin.resellers.'])) {
            return $role === AdminRole::Accountant && in_array($name, [
                'admin.resellers.index', 'admin.resellers.show', 'admin.resellers.withdrawals',
            ], true);
        }

        if ($this->startsWith($name, ['admin.billing.network.'])) {
            return $role === AdminRole::Infrastructure;
        }

        if ($this->startsWith($name, ['admin.billing.rates.', 'admin.billing.bundles.'])) {
            return false;
        }

        if ($this->startsWith($name, ['admin.tickets.', 'admin.support-teams.', 'admin.ticket-categories.', 'admin.incidents.'])) {
            return $role === AdminRole::Support;
        }

        if ($this->startsWith($name, ['admin.customers.', 'admin.projects.'])) {
            return in_array($role, [AdminRole::Accountant, AdminRole::Support, AdminRole::Infrastructure], true)
                && in_array($name, [
                    'admin.customers.index', 'admin.customers.show',
                    'admin.projects.index', 'admin.projects.show', 'admin.projects.proforma',
                ], true);
        }

        if ($this->startsWith($name, ['admin.virtual-machines.'])) {
            return $role === AdminRole::Infrastructure
                || ($role === AdminRole::Support && in_array($name, ['admin.virtual-machines.index', 'admin.virtual-machines.show'], true));
        }

        if ($this->startsWith($name, [
            'admin.proxmox-servers.', 'api.admin.proxmox-servers.', 'admin.hetzner-accounts.',
            'admin.infrastructure-locations.', 'admin.unprovisioned-virtual-machines.',
            'admin.cloud-images.', 'admin.ip-pools.',
        ])) {
            return $role === AdminRole::Infrastructure;
        }

        return false;
    }

    /** @param list<string> $prefixes */
    private function startsWith(string $name, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
