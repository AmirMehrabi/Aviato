<?php

namespace App\Support;

use App\Models\User;

class AdminNavigation
{
    public static function groups(User $user): array
    {
        $groups = [
            ['id' => 'overview', 'label' => null, 'secondary' => false, 'icon' => null, 'items' => [
                ['label' => 'داشبورد', 'route' => 'admin.dashboard', 'active' => request()->routeIs('admin.dashboard'), 'icon' => 'layout-dashboard', 'keywords' => 'dashboard overview'],
            ]],
            ['id' => 'customers', 'label' => 'مشتریان و همکاران', 'secondary' => false, 'icon' => null, 'items' => [
                ['label' => 'مشتریان', 'route' => 'admin.customers.index', 'active' => request()->routeIs('admin.customers.*'), 'icon' => 'users-round', 'keywords' => 'customers clients'],
                ['label' => 'فضاهای کاری', 'route' => 'admin.projects.index', 'active' => request()->routeIs('admin.projects.*'), 'icon' => 'folder-kanban', 'keywords' => 'projects workspaces'],
                ['label' => 'فروشندگان', 'route' => 'admin.resellers.index', 'active' => request()->routeIs('admin.resellers.*'), 'icon' => 'handshake', 'keywords' => 'resellers partners'],
            ]],
            ['id' => 'cloud-dns', 'label' => 'مدیریت DNS', 'secondary' => false, 'icon' => null, 'items' => [
                ['label' => 'مدیریت DNS', 'route' => 'admin.cloud-dns.index', 'active' => request()->routeIs('admin.cloud-dns.*'), 'icon' => 'globe', 'keywords' => 'DNS domains records دامنه'],
            ]],
            ['id' => 'compute', 'label' => 'زیرساخت', 'secondary' => false, 'icon' => null, 'items' => [
                ['label' => 'ماشین‌های مجازی', 'route' => 'admin.virtual-machines.index', 'active' => request()->routeIs('admin.virtual-machines.*'), 'icon' => 'monitor', 'keywords' => 'virtual machines VM servers'],
            ]],
            ['id' => 'support', 'label' => 'پشتیبانی و رخدادها', 'secondary' => false, 'icon' => null, 'items' => [
                ['label' => 'تیکت‌ها', 'route' => 'admin.tickets.index', 'active' => request()->routeIs('admin.tickets.*'), 'icon' => 'messages-square', 'keywords' => 'tickets support'],
                ['label' => 'احراز هویت حقوقی', 'route' => 'admin.corporate.index', 'active' => request()->routeIs('admin.corporate.*'), 'icon' => 'shield-check', 'keywords' => 'corporate verification company'],
                ['label' => 'رخدادهای سرویس', 'route' => 'admin.incidents.index', 'active' => request()->routeIs('admin.incidents.*'), 'icon' => 'triangle-alert', 'keywords' => 'incidents service status'],
            ]],
            ['id' => 'finance', 'label' => 'امور مالی', 'secondary' => false, 'icon' => null, 'items' => [
                ['label' => 'نمای کلی مالی', 'route' => 'admin.billing.overview', 'active' => request()->routeIs('admin.billing.overview'), 'icon' => 'chart-no-axes-combined', 'keywords' => 'finance billing overview'],
                ['label' => 'صورتحساب‌ها', 'route' => 'admin.billing.invoices.index', 'active' => request()->routeIs('admin.billing.invoices.*'), 'icon' => 'receipt-text', 'keywords' => 'invoices'],
                ['label' => 'پرداخت‌ها', 'route' => 'admin.billing.payments.index', 'active' => request()->routeIs('admin.billing.payments.*'), 'icon' => 'credit-card', 'keywords' => 'payments'],
                ['label' => 'کیف پول‌ها', 'route' => 'admin.billing.wallets.index', 'active' => request()->routeIs('admin.billing.wallets.*'), 'icon' => 'wallet', 'keywords' => 'wallets'],
                ['label' => 'تراکنش‌های کیف پول', 'route' => 'admin.billing.transactions.index', 'active' => request()->routeIs('admin.billing.transactions.*'), 'icon' => 'arrow-left-right', 'keywords' => 'wallet transactions'],
            ]],
            ['id' => 'infrastructure', 'label' => 'مدیریت زیرساخت', 'secondary' => true, 'icon' => 'server-cog', 'items' => [
                ['label' => 'سرورهای Proxmox', 'route' => 'admin.proxmox-servers.index', 'active' => request()->routeIs('admin.proxmox-servers.*'), 'icon' => 'server', 'keywords' => 'Proxmox hosts'],
                ['label' => 'حساب‌های Hetzner', 'route' => 'admin.hetzner-accounts.index', 'active' => request()->routeIs('admin.hetzner-accounts.*'), 'icon' => 'cloud', 'keywords' => 'Hetzner accounts providers'],
                ['label' => 'موقعیت‌های زیرساخت', 'route' => 'admin.infrastructure-locations.index', 'active' => request()->routeIs('admin.infrastructure-locations.*'), 'icon' => 'map-pinned', 'keywords' => 'infrastructure locations datacenter'],
                ['label' => 'مخزن‌های IP', 'route' => 'admin.ip-pools.index', 'active' => request()->routeIs('admin.ip-pools.*'), 'icon' => 'network', 'keywords' => 'IP pools network'],
                ['label' => 'ایمیج‌های ابری', 'route' => 'admin.cloud-images.index', 'active' => request()->routeIs('admin.cloud-images.*'), 'icon' => 'image', 'keywords' => 'cloud images OS templates'],
                ['label' => 'وارد کردن ماشین‌های موجود', 'route' => 'admin.unprovisioned-virtual-machines.index', 'active' => request()->routeIs('admin.unprovisioned-virtual-machines.*'), 'icon' => 'download', 'keywords' => 'import existing unregistered VMs'],
            ]],
            ['id' => 'support-settings', 'label' => 'تنظیمات پشتیبانی', 'secondary' => true, 'icon' => 'headset', 'items' => [
                ['label' => 'تیم‌های پشتیبانی', 'route' => 'admin.support-teams.index', 'active' => request()->routeIs('admin.support-teams.*'), 'icon' => 'headset', 'keywords' => 'support teams settings'],
                ['label' => 'دسته‌بندی تیکت‌ها', 'route' => 'admin.ticket-categories.index', 'active' => request()->routeIs('admin.ticket-categories.*'), 'icon' => 'tags', 'keywords' => 'ticket categories settings'],
            ]],
            ['id' => 'billing-tools', 'label' => 'ابزارهای صورتحساب', 'secondary' => true, 'icon' => 'chart-no-axes-combined', 'items' => [
                ['label' => 'مصرف و تسویه', 'route' => 'admin.billing.usage.index', 'active' => request()->routeIs('admin.billing.usage.*'), 'icon' => 'gauge', 'keywords' => 'usage settlement'],
                ['label' => 'حسابداری شبکه', 'route' => 'admin.billing.network.index', 'active' => request()->routeIs('admin.billing.network.*'), 'icon' => 'activity', 'keywords' => 'network billing traffic'],
            ]],
            ['id' => 'sales', 'label' => 'طرح‌ها و پروموشن‌ها', 'secondary' => true, 'icon' => 'package', 'items' => [
                ['label' => 'طرح‌ها و باندل‌ها', 'route' => 'admin.billing.bundles.index', 'active' => request()->routeIs('admin.billing.bundles.*'), 'icon' => 'package', 'keywords' => 'plans bundles'],
                ['label' => 'قیمت منابع', 'route' => 'admin.billing.rates.index', 'active' => request()->routeIs('admin.billing.rates.*'), 'icon' => 'badge-dollar-sign', 'keywords' => 'resource pricing rates price قیمت تعرفه'],
                ['label' => 'پروموشن و کارت هدیه', 'route' => 'admin.promotions.index', 'active' => request()->routeIs('admin.promotions.*'), 'icon' => 'gift', 'keywords' => 'promotions gift cards'],
            ]],
            ['id' => 'administration', 'label' => 'مدیریت سامانه', 'secondary' => true, 'icon' => 'settings-2', 'items' => [
                ['label' => 'تنظیمات', 'route' => 'admin.settings.edit', 'active' => request()->routeIs('admin.settings.*'), 'icon' => 'settings-2', 'keywords' => 'settings configuration'],
                ['label' => 'کاربران پنل', 'route' => 'admin.users.index', 'active' => request()->routeIs('admin.users.*'), 'icon' => 'shield-check', 'keywords' => 'admin users staff'],
                ['label' => 'گزارش فعالیت مدیران', 'route' => 'admin.audit.index', 'active' => request()->routeIs('admin.audit.*'), 'icon' => 'clipboard-list', 'keywords' => 'audit logs'],
                ['label' => 'فعالیت API', 'route' => 'admin.api-activity.index', 'active' => request()->routeIs('admin.api-activity.*'), 'icon' => 'square-terminal', 'keywords' => 'API activity'],
            ]],
        ];

        return collect($groups)->map(function (array $group) use ($user): array {
            $group['items'] = array_values(array_filter($group['items'], fn (array $item): bool => AdminAccess::canVisit($user, $item['route'])));
            $group['active'] = collect($group['items'])->contains('active', true);

            return $group;
        })->filter(fn (array $group): bool => $group['items'] !== [])->values()->all();
    }
}
