<?php

namespace App\Support;

use App\Models\User;

class AdminNavigation
{
    public static function groups(User $user): array
    {
        $groups = [
            ['id' => 'overview', 'label' => null, 'items' => [
                ['label' => 'داشبورد', 'route' => 'admin.dashboard', 'active' => request()->routeIs('admin.dashboard'), 'icon' => 'M3 3h8v8H3V3Zm10 0h8v8h-8V3ZM3 13h8v8H3v-8Zm10 0h8v8h-8v-8Z', 'keywords' => 'dashboard overview'],
            ]],
            ['id' => 'customers', 'label' => 'مشتریان و همکاران', 'items' => [
                ['label' => 'مشتریان', 'route' => 'admin.customers.index', 'active' => request()->routeIs('admin.customers.*'), 'icon' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'keywords' => 'customers clients'],
                ['label' => 'فضاهای کاری', 'route' => 'admin.projects.index', 'active' => request()->routeIs('admin.projects.*'), 'icon' => 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z', 'keywords' => 'projects workspaces'],
                ['label' => 'فروشندگان', 'route' => 'admin.resellers.index', 'active' => request()->routeIs('admin.resellers.*'), 'icon' => 'M3 9l1.5-5h15L21 9M3 9v12h18V9M9 21v-6h6v6M9 9V5h6v4', 'keywords' => 'resellers partners'],
            ]],
            ['id' => 'infrastructure', 'label' => 'زیرساخت ابری', 'items' => [
                ['label' => 'ماشین‌های مجازی', 'route' => 'admin.virtual-machines.index', 'active' => request()->routeIs('admin.virtual-machines.*'), 'icon' => 'M4 5h16v11H4V5Zm4 14h8', 'keywords' => 'virtual machines VM servers'],
                ['label' => 'وارد کردن ماشین‌های موجود', 'route' => 'admin.unprovisioned-virtual-machines.index', 'active' => request()->routeIs('admin.unprovisioned-virtual-machines.*'), 'icon' => 'M12 3v18M3 12h18', 'keywords' => 'import existing unregistered VMs'],
                ['label' => 'سرورهای Proxmox', 'route' => 'admin.proxmox-servers.index', 'active' => request()->routeIs('admin.proxmox-servers.*'), 'icon' => 'M4 6h16v4H4V6Zm0 5h16v4H4v-4Zm0 5h16v4H4v-4Z', 'keywords' => 'Proxmox hosts'],
                ['label' => 'مخزن‌های IP', 'route' => 'admin.ip-pools.index', 'active' => request()->routeIs('admin.ip-pools.*'), 'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M3 12h18', 'keywords' => 'IP pools network'],
                ['label' => 'ایمیج‌های ابری', 'route' => 'admin.cloud-images.index', 'active' => request()->routeIs('admin.cloud-images.*'), 'icon' => 'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm3 10 2.5-3 2 2 2.5-3 4 4H7Z', 'keywords' => 'cloud images OS templates'],
                ['label' => 'موقعیت‌های زیرساخت', 'route' => 'admin.infrastructure-locations.index', 'active' => request()->routeIs('admin.infrastructure-locations.*'), 'icon' => 'M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11Zm0-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z', 'keywords' => 'infrastructure locations datacenter'],
                ['label' => 'حساب‌های Hetzner', 'route' => 'admin.hetzner-accounts.index', 'active' => request()->routeIs('admin.hetzner-accounts.*'), 'icon' => 'M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10Z', 'keywords' => 'Hetzner accounts providers'],
            ]],
            ['id' => 'support', 'label' => 'پشتیبانی و رخدادها', 'items' => [
                ['label' => 'تیکت‌ها', 'route' => 'admin.tickets.index', 'active' => request()->routeIs('admin.tickets.*'), 'icon' => 'M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H8l-5 4V5Z', 'keywords' => 'tickets support'],
                ['label' => 'رخدادهای سرویس', 'route' => 'admin.incidents.index', 'active' => request()->routeIs('admin.incidents.*'), 'icon' => 'M12 3v18M3 12h18', 'keywords' => 'incidents service status'],
                ['label' => 'تیم‌های پشتیبانی', 'route' => 'admin.support-teams.index', 'active' => request()->routeIs('admin.support-teams.*'), 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1 2 2 4-4', 'keywords' => 'support teams settings', 'contextual' => true],
                ['label' => 'دسته‌بندی تیکت‌ها', 'route' => 'admin.ticket-categories.index', 'active' => request()->routeIs('admin.ticket-categories.*'), 'icon' => 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z', 'keywords' => 'ticket categories settings', 'contextual' => true],
            ]],
            ['id' => 'finance', 'label' => 'امور مالی', 'items' => [
                ['label' => 'نمای کلی مالی', 'route' => 'admin.billing.overview', 'active' => request()->routeIs('admin.billing.overview'), 'icon' => 'M4 19V9 M10 19V5 M16 19v-7 M22 19H2', 'keywords' => 'finance billing overview'],
                ['label' => 'صورتحساب‌ها', 'route' => 'admin.billing.invoices.index', 'active' => request()->routeIs('admin.billing.invoices.*'), 'icon' => 'M6 2h12v20l-3-2-3 2-3-2-3 2V2Zm3 6h6M9 12h6', 'keywords' => 'invoices'],
                ['label' => 'پرداخت‌ها', 'route' => 'admin.billing.payments.index', 'active' => request()->routeIs('admin.billing.payments.*'), 'icon' => 'M3 6h18v12H3V6Zm0 4h18 M7 15h3', 'keywords' => 'payments'],
                ['label' => 'کیف پول‌ها', 'route' => 'admin.billing.wallets.index', 'active' => request()->routeIs('admin.billing.wallets.*'), 'icon' => 'M3 6h16a2 2 0 0 1 2 2v11H3V6Zm0 0V4h14v2 M16 12h5', 'keywords' => 'wallets'],
                ['label' => 'تراکنش‌های کیف پول', 'route' => 'admin.billing.transactions.index', 'active' => request()->routeIs('admin.billing.transactions.*'), 'icon' => 'M4 7h15a2 2 0 0 1 2 2v10H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h13 M16 13h5', 'keywords' => 'wallet transactions'],
                ['label' => 'مصرف و تسویه', 'route' => 'admin.billing.usage.index', 'active' => request()->routeIs('admin.billing.usage.*'), 'icon' => 'M4 12a8 8 0 1 0 8-8v8H4Zm8-8a8 8 0 0 1 8 8h-8V4Z', 'keywords' => 'usage settlement'],
                ['label' => 'حسابداری شبکه', 'route' => 'admin.billing.network.index', 'active' => request()->routeIs('admin.billing.network.*'), 'icon' => 'M4 12h4l3-8 4 16 3-8h3 M5 20h14', 'keywords' => 'network billing traffic'],
            ]],
            ['id' => 'sales', 'label' => 'طرح‌ها و فروش', 'items' => [
                ['label' => 'طرح‌ها و باندل‌ها', 'route' => 'admin.billing.bundles.index', 'active' => request()->routeIs('admin.billing.bundles.*'), 'icon' => 'M16.5 9.4 12 2 7.5 9.4 M3 9.4h18v12H3V9.4Z M7.5 2v7.4 M16.5 2v7.4', 'keywords' => 'plans bundles'],
                ['label' => 'قیمت منابع', 'route' => 'admin.billing.rates.index', 'active' => request()->routeIs('admin.billing.rates.*'), 'icon' => 'M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z', 'keywords' => 'resource pricing rates price قیمت تعرفه'],
                ['label' => 'پروموشن و کارت هدیه', 'route' => 'admin.promotions.index', 'active' => request()->routeIs('admin.promotions.*'), 'icon' => 'M20 12v10H4V12M2 7h20v5H2V7Zm10 15V7m0 0c-2.5 0-5-1-5-3 0-1.2 1-2 2.3-2C11 2 12 4.5 12 7Zm0 0c2.5 0 5-1 5-3 0-1.2-1-2-2.3-2C13 2 12 4.5 12 7Z', 'keywords' => 'promotions gift cards'],
            ]],
            ['id' => 'administration', 'label' => 'مدیریت سامانه', 'items' => [
                ['label' => 'کاربران پنل', 'route' => 'admin.users.index', 'active' => request()->routeIs('admin.users.*'), 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1 2 2 4-4', 'keywords' => 'admin users staff'],
                ['label' => 'گزارش فعالیت مدیران', 'route' => 'admin.audit.index', 'active' => request()->routeIs('admin.audit.*'), 'icon' => 'M12 3a9 9 0 1 0 9 9M12 7v5l3 2', 'keywords' => 'audit logs'],
                ['label' => 'فعالیت API', 'route' => 'admin.api-activity.index', 'active' => request()->routeIs('admin.api-activity.*'), 'icon' => 'M4 5h16v14H4V5Zm4 4h8M8 13h5', 'keywords' => 'API activity'],
                ['label' => 'تنظیمات', 'route' => 'admin.settings.edit', 'active' => request()->routeIs('admin.settings.*'), 'icon' => 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M12 8V5 M12 16v3 M8 12H5 M16 12h3 M9.2 9.2l-1.8-1.8 M14.8 14.8l1.8 1.8 M9.2 14.8l-1.8 1.8 M14.8 9.2l1.8-1.8', 'keywords' => 'settings configuration'],
            ]],
        ];

        return collect($groups)->map(function (array $group) use ($user): array {
            $group['items'] = array_values(array_filter($group['items'], fn (array $item): bool => AdminAccess::canVisit($user, $item['route'])));
            $group['active'] = collect($group['items'])->contains('active', true);

            return $group;
        })->filter(fn (array $group): bool => $group['items'] !== [])->values()->all();
    }
}
