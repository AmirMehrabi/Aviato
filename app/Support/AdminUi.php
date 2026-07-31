<?php

namespace App\Support;

class AdminUi
{
    public static function status(mixed $value): string
    {
        return self::statusMeta($value)['label'];
    }

    /** @return array{label: string, tone: string, description: string|null} */
    public static function statusMeta(mixed $value): array
    {
        $value = strtolower(trim((string) $value));
        $labels = [
            'active' => 'فعال', 'inactive' => 'غیرفعال', 'available' => 'آزاد',
            'reserved' => 'رزروشده', 'assigned' => 'تخصیص‌یافته', 'released' => 'آزادشده',
            'running' => 'روشن', 'stopped' => 'خاموش', 'suspended' => 'تعلیق‌شده',
            'deleting' => 'در حال حذف', 'deleted' => 'حذف‌شده', 'pending' => 'در انتظار',
            'provisioning' => 'در حال ساخت', 'ready' => 'آماده', 'failed' => 'ناموفق',
            'successful' => 'موفق', 'cancelled' => 'لغوشده', 'paid' => 'پرداخت‌شده',
            'draft' => 'پیش‌نویس', 'issued' => 'صادرشده', 'settled' => 'تسویه‌شده',
            'unsettled' => 'تسویه‌نشده', 'online' => 'آنلاین', 'offline' => 'آفلاین',
            'unknown' => 'نامشخص', 'synced' => 'همگام', 'open' => 'باز',
            'closed' => 'بسته', 'resolved' => 'حل‌شده', 'approved' => 'تأییدشده',
            'rejected' => 'ردشده', 'processing' => 'در حال پردازش', 'completed' => 'تکمیل‌شده',
            'enabled' => 'فعال', 'disabled' => 'غیرفعال', 'published' => 'منتشرشده',
            'locked' => 'قفل‌شده', 'investigating' => 'در حال بررسی',
            'identified' => 'شناسایی‌شده', 'monitoring' => 'در حال پایش',
        ];

        $tone = match ($value) {
            'active', 'available', 'running', 'ready', 'successful', 'paid', 'settled',
            'online', 'synced', 'resolved', 'approved', 'completed', 'enabled', 'published' => 'success',
            'stopped', 'suspended', 'failed', 'rejected', 'locked' => 'danger',
            'pending', 'provisioning', 'deleting', 'unsettled', 'draft', 'investigating',
            'identified', 'monitoring' => 'warning',
            'reserved', 'assigned', 'issued', 'open', 'processing' => 'info',
            default => 'neutral',
        };

        $description = match ($value) {
            'running' => 'ماشین مجازی روشن و در حال سرویس‌دهی است.',
            'stopped' => 'ماشین مجازی خاموش است.',
            'suspended' => 'دسترسی این مورد به‌صورت مدیریتی تعلیق شده است.',
            'pending' => 'این عملیات هنوز نهایی نشده است.',
            'failed' => 'عملیات با خطا پایان یافته است.',
            default => null,
        };

        return [
            'label' => $labels[$value] ?? ($value !== '' ? $value : 'نامشخص'),
            'tone' => $tone,
            'description' => $description,
        ];
    }
}
