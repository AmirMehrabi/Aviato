<?php

namespace App\Services\Sms;

use App\Models\Project;

class WalletSmsTemplates
{
    public const RECOVERY = 'aviato-credit-recovery-v3';

    public const DAYS = 'aviato-credit-days-v3';

    public const FROZEN = 'aviato-credit-network-v3';

    public const STOPPED = 'aviato-credit-stopped-v3';

    public static function recoveryToken(Project $project): string
    {
        return str_replace('-', '', $project->uuid);
    }

    public static function definitions(): array
    {
        $link = 'https://'.config('portals.customer.domain').'/wallet/recover/%token3';

        return [
            'recovery' => ['name' => self::RECOVERY, 'text' => "%token عزیز\nموجودی قابل استفاده فضای کاری «%token10»: %token20 تومان\nشارژ کیف پول: {$link}\nآویاتو"],
            'days' => ['name' => self::DAYS, 'text' => "%token عزیز\nاعتبار فضای کاری «%token10» رو به اتمام است.\nاعتبار باقی‌مانده: %token20 تومان\nبا مصرف فعلی سرویس‌ها، اعتبار شما برای حدود %token2 روز کامل کافی است؛ صفر یعنی کمتر از یک روز.\nبرای ادامه دسترسی بدون وقفه، لطفاً کیف پول را شارژ کنید.\nشارژ کیف پول: {$link}\nآویاتو"],
            'frozen' => ['name' => self::FROZEN, 'text' => "%token عزیز\nاعتبار قابل استفاده کیف پول شما تمام شده و دسترسی شبکه سرور «%token10» موقتاً قطع شده است.\nبرای برقراری دوباره دسترسی، لطفاً کیف پول را شارژ کنید تا موجودی قابل استفاده مثبت شود.\nشارژ کیف پول: {$link}\nآویاتو"],
            'stopped' => ['name' => self::STOPPED, 'text' => "%token عزیز\nسرور «%token10» به دلیل کافی نبودن اعتبار خاموش شد.\nبرای راه‌اندازی دوباره، لطفاً کیف پول را شارژ کنید تا موجودی قابل استفاده مثبت شود. هزینه نگهداری دیسک و بکاپ همچنان محاسبه می‌شود.\nشارژ کیف پول: {$link}\nآویاتو"],
            'restored' => ['name' => 'aviato-credit-restored-v2', 'text' => "%token عزیز\nمحدودیت اعتبار سرور «%token10» برطرف شد و دسترسی شبکه دوباره برقرار است. سرورهایی که به دلیل اعتبار خاموش شده بودند، دوباره راه‌اندازی شده‌اند.\nاز همراهی شما سپاسگزاریم.\nآویاتو"],
        ];
    }
}
