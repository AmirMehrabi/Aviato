<?php

namespace App\Services\Domains;

class DomainRegistrant
{
    public static function countries(): array
    {
        $countries = ['IR' => 'ایران', 'AE' => 'امارات متحده عربی', 'TR' => 'ترکیه', 'AM' => 'ارمنستان', 'DE' => 'آلمان', 'FR' => 'فرانسه', 'NL' => 'هلند', 'GB' => 'بریتانیا', 'US' => 'ایالات متحده', 'CA' => 'کانادا', 'AU' => 'استرالیا'];
        if (class_exists(\ResourceBundle::class)) {
            $resource = \ResourceBundle::create('fa', 'ICUDATA-region');
            $bundle = $resource instanceof \ResourceBundle ? $resource->get('Countries') : null;
            if ($bundle) {
                foreach ($bundle as $code => $label) {
                    if (preg_match('/^[A-Z]{2}$/', (string) $code)) {
                        $countries[$code] = $label;
                    }
                }
            }
        }

        return $countries;
    }

    public static function phone(string $phone, string $country): string
    {
        $phone = strtr($phone, array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
        $phone = preg_replace('/[\s()\-]+/u', '', $phone);
        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        }
        if ($country === 'IR' && preg_match('/^09[0-9]{9}$/', $phone)) {
            return '+98.'.substr($phone, 1);
        }
        $prefix = ['IR' => '98', 'AE' => '971', 'TR' => '90', 'AM' => '374', 'DE' => '49', 'FR' => '33', 'NL' => '31', 'GB' => '44', 'US' => '1', 'CA' => '1', 'AU' => '61'][$country] ?? null;
        if ($prefix && ! str_contains($phone, '.') && str_starts_with($phone, '+'.$prefix)) {
            return '+'.$prefix.'.'.substr($phone, strlen($prefix) + 1);
        }

        return $phone;
    }
}
