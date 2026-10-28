<?php

namespace App\Services\Corporate;

use App\Models\AppSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class CompanyInquiryClient
{
    public function lookup(string $nationalId): array
    {
        $token = AppSetting::nationalCodeVerificationToken();
        if ($token === '') {
            throw ValidationException::withMessages(['national_id' => 'توکن استعلام شرکت تنظیم نشده است.']);
        }
        try {
            $response = Http::acceptJson()->asJson()->withToken($token)->timeout(15)
                ->post('https://service.zohal.io/api/v0/services/inquiry/company_inquiry', ['national_id' => $nationalId]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['national_id' => 'سرویس استعلام در دسترس نیست؛ دوباره تلاش کنید.']);
        }
        $company = $response->json('response_body.data');
        if (is_array($company)) {
            // Zohal can represent missing fields as the literal string "null".
            $company = array_map(fn ($value) => is_string($value) && strtolower(trim($value)) === 'null' ? null : $value, $company);
        }
        if (! $response->successful() || (int) $response->json('result') !== 1 || ! is_array($company)
            || ($company['national_id'] ?? '') !== $nationalId || blank($company['name'] ?? null)) {
            throw ValidationException::withMessages(['national_id' => 'استعلام معتبر شرکت دریافت نشد؛ شناسه را بررسی و دوباره تلاش کنید.']);
        }
        if (filled($company['activity_end_date'] ?? null)) {
            throw ValidationException::withMessages(['national_id' => 'برای این شرکت تاریخ پایان فعالیت ثبت شده است؛ با پشتیبانی تماس بگیرید.']);
        }

        return Arr::only($company, ['national_id', 'name', 'register_number', 'register_date', 'company_type', 'address', 'postal_code', 'phone_number', 'email_address', 'activity_end_date']);
    }
}
