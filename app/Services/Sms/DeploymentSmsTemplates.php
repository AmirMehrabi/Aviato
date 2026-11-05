<?php

namespace App\Services\Sms;

class DeploymentSmsTemplates
{
    public static function definitions(): array
    {
        return [
            'started' => ['name' => 'aviato-deploy-started-v1', 'text' => "استقرار آویاتو آغاز شد.\nشناسه تغییرات: %token\nشماره انتشار فعال: %token2\nنتیجه پس از پایان بررسی‌ها اعلام می‌شود."],
            'success' => ['name' => 'aviato-deploy-success-v1', 'text' => "استقرار آویاتو با موفقیت انجام شد.\nشناسه تغییرات: %token\nشماره انتشار فعال: %token2\nبررسی سلامت سرویس‌ها موفق بود."],
            'failure' => ['name' => 'aviato-deploy-failure-v1', 'text' => "استقرار آویاتو ناموفق بود.\nشناسه تغییرات: %token\nشماره انتشار فعال: %token2\nلطفاً گزارش استقرار را بررسی کنید."],
            'rollback' => ['name' => 'aviato-deploy-rollback-v1', 'text' => "پس از خطای استقرار آویاتو، انتشار قبلی دوباره فعال شد.\nشناسه تغییرات ناموفق: %token\nشماره انتشار فعال: %token2\nلطفاً گزارش استقرار را بررسی کنید."],
            'recovery' => ['name' => 'aviato-deploy-recovery-v1', 'text' => "استقرار آویاتو پس از خطای قبلی با موفقیت انجام شد.\nشناسه تغییرات: %token\nشماره انتشار فعال: %token2\nبررسی سلامت سرویس‌ها موفق بود."],
        ];
    }
}
