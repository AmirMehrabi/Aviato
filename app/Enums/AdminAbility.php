<?php

namespace App\Enums;

enum AdminAbility: string
{
    case Dashboard = 'dashboard.view';
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';
    case BillingRead = 'billing.read';
    case BillingExport = 'billing.export';
    case BillingManage = 'billing.manage';
    case WalletManage = 'wallet.manage';
    case CustomersRead = 'customers.read';
    case CustomersManage = 'customers.manage';
    case CustomersDelete = 'customers.delete';
    case CustomersCredentials = 'customers.credentials';
    case CustomersSuspend = 'customers.suspend';
    case CustomersImpersonate = 'customers.impersonate';
    case ProjectsRead = 'projects.read';
    case ProjectsManage = 'projects.manage';
    case TicketsRead = 'tickets.read';
    case TicketsManage = 'tickets.manage';
    case SupportManage = 'support.manage';
    case IncidentsRead = 'incidents.read';
    case IncidentsManage = 'incidents.manage';
    case VmRead = 'virtual-machines.read';
    case VmManage = 'virtual-machines.manage';
    case VmPower = 'virtual-machines.power';
    case VmConsole = 'virtual-machines.console';
    case VmTransfer = 'virtual-machines.transfer';
    case VmDelete = 'virtual-machines.delete';
    case InfrastructureRead = 'infrastructure.read';
    case InfrastructureManage = 'infrastructure.manage';
    case NetworkRead = 'network.read';
    case NetworkManage = 'network.manage';
    case ResellersRead = 'resellers.read';
    case ResellersManage = 'resellers.manage';
    case PromotionsManage = 'promotions.manage';
    case SettingsManage = 'settings.manage';
    case PricingManage = 'pricing.manage';
    case ApiActivityView = 'api-activity.view';
    case HetznerRead = 'hetzner.read';
    case HetznerManage = 'hetzner.manage';
    case LocationsRead = 'locations.read';
    case LocationsManage = 'locations.manage';
    case ImagesRead = 'cloud-images.read';
    case ImagesManage = 'cloud-images.manage';
    case IpPoolsRead = 'ip-pools.read';
    case IpPoolsManage = 'ip-pools.manage';
    case BundlesManage = 'bundles.manage';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'مشاهده داشبورد',
            self::UsersManage => 'مدیریت حساب‌ها',
            self::AuditView => 'مشاهده ردپای مدیریتی',
            self::BillingRead => 'مشاهده سوابق مالی و موجودی',
            self::BillingExport => 'خروجی سوابق مالی',
            self::BillingManage => 'عملیات حسابداری پرداخت‌ها',
            self::WalletManage => 'تغییر موجودی، قفل و هشدار کیف پول',
            self::CustomersRead => 'مشاهده مشتریان',
            self::CustomersManage => 'ایجاد و ویرایش مشتریان',
            self::CustomersDelete => 'حذف مشتریان',
            self::CustomersCredentials => 'تغییر رمز عبور مشتریان',
            self::CustomersSuspend => 'تعلیق و فعال‌سازی مشتریان',
            self::CustomersImpersonate => 'ورود به حساب مشتری',
            self::ProjectsRead => 'مشاهده فضاهای کاری',
            self::ProjectsManage => 'ویرایش و مدیریت اعضا',
            self::TicketsRead => 'مشاهده تیکت‌ها',
            self::TicketsManage => 'پاسخ و مدیریت تیکت‌ها',
            self::SupportManage => 'مدیریت تیم‌ها و دسته‌بندی‌ها',
            self::IncidentsRead => 'مشاهده رخدادها',
            self::IncidentsManage => 'مدیریت رخدادها',
            self::VmRead => 'مشاهده ماشین‌ها',
            self::VmManage => 'ایجاد، ویرایش و آماده‌سازی',
            self::VmPower => 'روشن و خاموش کردن',
            self::VmConsole => 'دسترسی کنسول',
            self::VmTransfer => 'انتقال مالکیت و جابه‌جایی نود',
            self::VmDelete => 'حذف ماشین‌ها',
            self::InfrastructureRead => 'مشاهده سرورها و ماشین‌های ثبت‌نشده',
            self::InfrastructureManage => 'مدیریت سرورها',
            self::NetworkRead => 'مشاهده مصرف و حسابداری شبکه',
            self::NetworkManage => 'عملیات حسابداری شبکه',
            self::ResellersRead => 'مشاهده فروشندگان',
            self::ResellersManage => 'مدیریت فروشندگان',
            self::PromotionsManage => 'مدیریت پروموشن و کارت هدیه',
            self::SettingsManage => 'مدیریت تنظیمات',
            self::PricingManage => 'مدیریت قیمت منابع و باندل‌ها',
            self::ApiActivityView => 'مشاهده فعالیت API',
            self::HetznerRead => 'مشاهده',
            self::HetznerManage => 'ایجاد، ویرایش و حذف',
            self::LocationsRead => 'مشاهده',
            self::LocationsManage => 'ایجاد، ویرایش و حذف',
            self::ImagesRead => 'مشاهده',
            self::ImagesManage => 'ایجاد، ویرایش و حذف',
            self::IpPoolsRead => 'مشاهده',
            self::IpPoolsManage => 'ایجاد، ویرایش و حذف',
            self::BundlesManage => 'مدیریت باندل‌ها',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Dashboard => 'عمومی',
            self::UsersManage => 'کاربران پنل',
            self::AuditView => 'سیستم',
            self::BillingRead => 'مالی',
            self::BillingExport => 'مالی',
            self::BillingManage => 'مالی',
            self::WalletManage => 'مالی',
            self::CustomersRead => 'مشتریان',
            self::CustomersManage => 'مشتریان',
            self::CustomersDelete => 'مشتریان',
            self::CustomersCredentials => 'مشتریان',
            self::CustomersSuspend => 'مشتریان',
            self::CustomersImpersonate => 'مشتریان',
            self::ProjectsRead => 'فضاهای کاری',
            self::ProjectsManage => 'فضاهای کاری',
            self::TicketsRead => 'پشتیبانی',
            self::TicketsManage => 'پشتیبانی',
            self::SupportManage => 'پشتیبانی',
            self::IncidentsRead => 'رخدادها',
            self::IncidentsManage => 'رخدادها',
            self::VmRead => 'ماشین‌های مجازی',
            self::VmManage => 'ماشین‌های مجازی',
            self::VmPower => 'ماشین‌های مجازی',
            self::VmConsole => 'ماشین‌های مجازی',
            self::VmTransfer => 'ماشین‌های مجازی',
            self::VmDelete => 'ماشین‌های مجازی',
            self::InfrastructureRead => 'Proxmox',
            self::InfrastructureManage => 'Proxmox',
            self::NetworkRead => 'حسابداری شبکه',
            self::NetworkManage => 'حسابداری شبکه',
            self::ResellersRead => 'فروشندگان',
            self::ResellersManage => 'فروشندگان',
            self::PromotionsManage => 'پروموشن',
            self::SettingsManage => 'سیستم',
            self::PricingManage => 'مالی',
            self::ApiActivityView => 'سیستم',
            self::HetznerRead => 'Hetzner',
            self::HetznerManage => 'Hetzner',
            self::LocationsRead => 'موقعیت‌های زیرساخت',
            self::LocationsManage => 'موقعیت‌های زیرساخت',
            self::ImagesRead => 'ایمیج‌های ابری',
            self::ImagesManage => 'ایمیج‌های ابری',
            self::IpPoolsRead => 'مخزن‌های IP',
            self::IpPoolsManage => 'مخزن‌های IP',
            self::BundlesManage => 'باندل‌ها',
        };
    }
}
