<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class NationalCodeVerificationClient
{
    public function verify(string $mobile, string $nationalCode): void
    {
        $token = AppSetting::nationalCodeVerificationToken();

        if ($token === '') {
            throw new RuntimeException('تنظیمات استعلام کد ملی کامل نیست.');
        }

        $normalizedMobile = $this->normalizeIranianPhone($mobile);

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(15)
                ->withToken($token)
                ->post('https://service.zohal.io/api/v0/services/inquiry/shahkar', [
                    'mobile' => $normalizedMobile,
                    'national_code' => $nationalCode,
                ]);
        } catch (Throwable) {
            throw new RuntimeException('اتصال به سرویس استعلام کد ملی ناموفق بود.');
        }

        $payload = $response->json();
        $result = data_get($payload, 'result');
        $matched = data_get($payload, 'response_body.data.matched');

        if ((int) $result === 1 && $matched === true) {
            return;
        }

        $message = (string) data_get($payload, 'response_body.message', 'پاسخ نامعتبر از سرویس استعلام کد ملی دریافت شد.');

        throw new RuntimeException($message);
    }

    private function normalizeIranianPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '98')) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        if (! preg_match('/^09\d{9}$/', $digits)) {
            throw new RuntimeException('فرمت شماره موبایل برای استعلام کد ملی معتبر نیست.');
        }

        return $digits;
    }
}
