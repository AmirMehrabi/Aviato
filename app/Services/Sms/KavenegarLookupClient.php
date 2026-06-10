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

        $this->sendLookup($phone, $template, $code);
    }

    public function sendLookup(string $phone, string $template, string $token, ?string $token2 = null, ?string $token3 = null): void
    {
        $apiKey = (string) AppSetting::getValue(AppSetting::KAVENEGAR_API_KEY, '');

        if ($apiKey === '' || $template === '') {
            throw new RuntimeException('تنظیمات درگاه Kavenegar کامل نیست.');
        }

        $payload = [
            'receptor' => $this->normalizeIranianPhone($phone),
            'token' => $token,
            'template' => $template,
        ];

        if (filled($token2)) {
            $payload['token2'] = $token2;
        }

        if (filled($token3)) {
            $payload['token3'] = $token3;
        }

        $this->sendPayload($apiKey, $payload);
    }

    public function sendLookupWithSpacedToken(string $phone, string $template, string $token): void
    {
        $apiKey = (string) AppSetting::getValue(AppSetting::KAVENEGAR_API_KEY, '');
        $token = trim(preg_replace('/\s+/u', ' ', $token) ?? $token);

        if ($apiKey === '' || $template === '' || $token === '') {
            throw new RuntimeException('تنظیمات درگاه Kavenegar کامل نیست.');
        }

        $payload = [
            'receptor' => $this->normalizeIranianPhone($phone),
            'token' => $this->compactToken($token),
            'token10' => $token,
            'template' => $template,
        ];

        $this->sendPayload($apiKey, $payload);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function sendPayload(string $apiKey, array $payload): void
    {
        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post('https://api.kavenegar.com/v1/'.rawurlencode($apiKey).'/verify/lookup.json', $payload);
        } catch (Throwable) {
            throw new RuntimeException('اتصال به درگاه Kavenegar ناموفق بود.');
        }

        $responsePayload = $response->json();
        $message = (string) data_get($responsePayload, 'return.message', '');

        if (! $response->ok()) {
            throw new RuntimeException($message !== ''
                ? 'ارسال پیامک Kavenegar ناموفق بود: '.$message
                : 'ارسال پیامک Kavenegar ناموفق بود. کد خطا: '.$response->status());
        }

        $status = data_get($responsePayload, 'return.status');
        if ((int) $status !== 200) {
            $message = (string) data_get($responsePayload, 'return.message', 'پاسخ نامعتبر از درگاه Kavenegar دریافت شد.');

            throw new RuntimeException($message);
        }
    }

    private function compactToken(string $token): string
    {
        $compact = preg_replace('/[\s_[:punct:]]+/u', '', $token) ?? '';

        return mb_substr($compact !== '' ? $compact : 'customer', 0, 100);
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
