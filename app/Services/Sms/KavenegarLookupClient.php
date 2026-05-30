<?php

namespace App\Services\Sms;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class KavenegarLookupClient
{
    public function sendVerificationCode(string $phone, string $code): void
    {
        $apiKey = (string) AppSetting::getValue(AppSetting::KAVENEGAR_API_KEY, '');
        $template = (string) AppSetting::getValue(AppSetting::KAVENEGAR_TEMPLATE, '');

        if ($apiKey === '' || $template === '') {
            throw new RuntimeException('تنظیمات درگاه Kavenegar کامل نیست.');
        }

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post('https://api.kavenegar.com/v1/'.rawurlencode($apiKey).'/verify/lookup.json', [
                    'receptor' => $this->normalizeIranianPhone($phone),
                    'token' => $code,
                    'template' => $template,
                ]);
        } catch (Throwable) {
            throw new RuntimeException('اتصال به درگاه Kavenegar ناموفق بود.');
        }

        if (! $response->ok()) {
            throw new RuntimeException('ارسال پیامک Kavenegar ناموفق بود. کد خطا: '.$response->status());
        }

        $payload = $response->json();
        $status = data_get($payload, 'return.status');
        if ((int) $status !== 200) {
            $message = (string) data_get($payload, 'return.message', 'پاسخ نامعتبر از درگاه Kavenegar دریافت شد.');

            throw new RuntimeException($message);
        }
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
            throw new RuntimeException('فرمت شماره موبایل برای ارسال پیامک معتبر نیست.');
        }

        return $digits;
    }
}
