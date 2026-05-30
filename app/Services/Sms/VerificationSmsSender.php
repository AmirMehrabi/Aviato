<?php

namespace App\Services\Sms;

use App\Models\AppSetting;
use RuntimeException;

class VerificationSmsSender
{
    public function send(string $phone, string $code): void
    {
        match (AppSetting::smsGateway()) {
            'sms0098' => app(Sms0098Client::class)->sendVerificationCode($phone, $code),
            'kavenegar' => app(KavenegarLookupClient::class)->sendVerificationCode($phone, $code),
            default => throw new RuntimeException('درگاه پیامک انتخاب‌شده معتبر نیست.'),
        };
    }
}
