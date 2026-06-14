<?php

namespace App\Services\Sms;

use App\Models\AppSetting;
use RuntimeException;
use SoapClient;
use Throwable;

class Sms0098Client
{
    public function sendVerificationCode(string $phone, string $code): void
    {
        $username = (string) AppSetting::getValue(AppSetting::SMS0098_USERNAME, '');
        $password = (string) AppSetting::getValue(AppSetting::SMS0098_PASSWORD, '');
        $panelNo = (string) AppSetting::getValue(AppSetting::SMS0098_PANEL_NO, '');

        if ($username === '' || $password === '' || $panelNo === '') {
            throw new RuntimeException('تنظیمات پنل SMS0098 کامل نیست.');
        }

        $mobile = $this->normalizeIranianPhone($phone);
        if (! class_exists(SoapClient::class)) {
            throw new RuntimeException('افزونه SOAP در PHP فعال نیست.');
        }

        $text = "کد تایید حساب شما: {$code}\nاعتبار: 10 دقیقه";

        try {
            $result = $this->sendWithWsdl($username, $password, $panelNo, $mobile, $text);
        } catch (Throwable) {
            // Some hosts cannot fetch remote WSDL files. Fallback to non-WSDL SOAP mode.
            $result = $this->sendWithoutWsdl($username, $password, $panelNo, $mobile, $text);
        }

        if (! is_scalar($result)) {
            throw new RuntimeException('پاسخ نامعتبر از درگاه SMS0098 دریافت شد.');
        }

        $resultString = trim((string) $result);
        if (ctype_digit($resultString) && strlen($resultString) >= 9) {
            return;
        }

        if ($resultString !== '0') {
            throw new RuntimeException('ارسال پیامک ناموفق بود. کد خطا: '.$resultString);
        }
    }

    private function sendWithWsdl(string $username, string $password, string $panelNo, string $mobile, string $text): mixed
    {
        $client = new SoapClient('https://webservice.0098sms.com/service.asmx?wsdl', [
            'encoding' => 'UTF-8',
            'cache_wsdl' => WSDL_CACHE_NONE,
            'exceptions' => true,
        ]);

        return $client->SendSMSWithID([
            'username' => $username,
            'password' => $password,
            'mobileno' => $mobile,
            'pnlno' => $panelNo,
            'text' => $text,
            'isflash' => false,
        ])->SendSMSWithIDResult ?? null;
    }

    private function sendWithoutWsdl(string $username, string $password, string $panelNo, string $mobile, string $text): mixed
    {
        $client = new SoapClient(null, [
            'location' => 'https://webservice.0098sms.com/service.asmx',
            'uri' => 'http://tempuri.org/',
            'encoding' => 'UTF-8',
            'exceptions' => true,
            'trace' => false,
        ]);

        $response = $client->__soapCall('SendSMSWithID', [[
            'username' => $username,
            'password' => $password,
            'mobileno' => $mobile,
            'pnlno' => $panelNo,
            'text' => $text,
            'isflash' => false,
        ]], [
            'soapaction' => 'http://tempuri.org/SendSMSWithID',
        ]);

        return is_object($response) && property_exists($response, 'SendSMSWithIDResult')
            ? $response->SendSMSWithIDResult
            : $response;
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
