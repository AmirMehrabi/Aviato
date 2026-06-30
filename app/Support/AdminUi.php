<?php

namespace App\Support;

class AdminUi
{
    public static function status(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return [
            'active' => 'فعال',
            'inactive' => 'غیرفعال',
            'available' => 'آزاد',
            'reserved' => 'رزروشده',
            'assigned' => 'تخصیص‌یافته',
            'released' => 'آزادشده',
            'running' => 'روشن',
            'stopped' => 'خاموش',
            'suspended' => 'تعلیق‌شده',
            'deleting' => 'در حال حذف',
            'pending' => 'در انتظار',
            'provisioning' => 'در حال ساخت',
            'ready' => 'آماده',
            'failed' => 'ناموفق',
            'successful' => 'موفق',
            'cancelled' => 'لغوشده',
            'paid' => 'پرداخت‌شده',
            'draft' => 'پیش‌نویس',
            'issued' => 'صادرشده',
            'settled' => 'تسویه‌شده',
            'unsettled' => 'تسویه‌نشده',
            'online' => 'آنلاین',
            'offline' => 'آفلاین',
            'unknown' => 'نامشخص',
            'synced' => 'همگام',
            'open' => 'باز',
            'closed' => 'بسته',
            'resolved' => 'حل‌شده',
            'approved' => 'تأییدشده',
            'rejected' => 'ردشده',
            'processing' => 'در حال پردازش',
            'completed' => 'تکمیل‌شده',
            'enabled' => 'فعال',
            'disabled' => 'غیرفعال',
        ][$value] ?? ($value !== '' ? $value : 'نامشخص');
    }
}
