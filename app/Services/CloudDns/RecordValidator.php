<?php

namespace App\Services\CloudDns;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordValidator
{
    public const TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'CAA', 'SRV', 'NS'];

    public function domain(string $value): string
    {
        $value = strtolower(rtrim(trim($value), '.'));
        $ascii = idn_to_ascii($value, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
        if ($ascii === false || strlen($ascii) > 253 || ! str_contains($ascii, '.') || filter_var($ascii, FILTER_VALIDATE_IP) || ! $this->hostname($ascii)) {
            $this->invalid('name', 'نام دامنه معتبر وارد کنید؛ بدون پروتکل، مسیر یا پورت.');
        }

        return $ascii;
    }

    public function rrset(string $zone, array $input): array
    {
        $input['type'] = strtoupper($input['type'] ?? '');
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:254'],
            'type' => ['required', Rule::in(self::TYPES)],
            'ttl' => ['required', 'integer', 'between:60,86400'],
            'content' => ['required', 'string', 'max:60000'],
        ])->validate();
        $name = strtolower(trim($data['name']));
        if ($name === '@') {
            $name = $zone.'.';
        } elseif (! str_ends_with($name, '.')) {
            $name .= '.'.$zone.'.';
        }
        $bare = rtrim($name, '.');
        $labels = explode('.', $bare);
        foreach ($labels as $index => $label) {
            if (($label === '*' && $index === 0) || preg_match('/^_?[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label)) {
                continue;
            }
            $this->invalid('name', 'نام رکورد معتبر نیست.');
        }
        if (strlen($bare) > 253 || ($bare !== $zone && ! str_ends_with($bare, '.'.$zone))) {
            $this->invalid('name', 'رکورد باید داخل همین دامنه باشد.');
        }
        if ($bare === $zone && in_array($data['type'], ['CNAME', 'NS'], true)) {
            $this->invalid('type', 'NS اصلی توسط سامانه مدیریت می‌شود و CNAME در ریشه مجاز نیست.');
        }
        $contents = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $data['content'])), fn ($v) => $v !== '')));
        if (count($contents) > 100 || $contents === [] || ($data['type'] === 'CNAME' && count($contents) !== 1)) {
            $this->invalid('content', 'تعداد مقدارهای رکورد معتبر نیست؛ CNAME فقط یک مقصد دارد.');
        }
        $records = array_map(fn ($content) => $this->content($data['type'], $content), $contents);

        return ['name' => $name, 'type' => $data['type'], 'ttl' => (int) $data['ttl'], 'records' => $records];
    }

    public function content(string $type, string $value): string
    {
        $valid = match ($type) {
            'A' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'AAAA' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            'CNAME', 'NS' => $this->hostname(rtrim($value, '.')),
            'MX' => preg_match('/^(\d{1,5})\s+(\S+)$/D', $value, $m) && (int) $m[1] <= 65535 && ($m[2] === '.' || $this->hostname(rtrim($m[2], '.'))),
            'SRV' => preg_match('/^(\d{1,5})\s+(\d{1,5})\s+(\d{1,5})\s+(\S+)$/D', $value, $m) && max((int) $m[1], (int) $m[2], (int) $m[3]) <= 65535 && ($m[4] === '.' || $this->hostname(rtrim($m[4], '.'))),
            'CAA' => preg_match('/^(\d{1,3})\s+([a-zA-Z0-9]+)\s+"[^"\r\n\\\\]*"$/D', $value, $m) && (int) $m[1] <= 255,
            'TXT' => strlen($value) <= 4096 && ! preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value),
            default => false,
        };
        if (! $valid) {
            $this->invalid('content', 'مقدار رکورد '.$type.' معتبر نیست.');
        }
        if ($type === 'TXT') {
            // Accept human-readable TXT; store canonical DNS presentation with 255-byte chunks.
            if (str_starts_with($value, '"')) {
                if (! preg_match('/^(?:"(?:[^"\\\\]|\\\\[\x20-\x7e])*"\s*)+$/D', $value)) {
                    $this->invalid('content', 'مقدار TXT نقل‌قول‌شده معتبر نیست.');
                }
                preg_match_all('/"((?:[^"\\\\]|\\\\[\x20-\x7e])*)"/', $value, $chunks);
                $value = implode('', array_map(fn ($s) => $this->decodeTxt($s), $chunks[1]));
            }

            return implode(' ', array_map(function ($part): string {
                $escaped = preg_replace_callback('/[\x00-\x1f\x7f-\xff"\\\\]/', function ($match): string {
                    return in_array($match[0], ['"', '\\'], true) ? '\\'.$match[0] : sprintf('\\%03d', ord($match[0]));
                }, $part);

                return '"'.$escaped.'"';
            }, $value === '' ? [''] : str_split($value, 255)));
        }
        if (in_array($type, ['CNAME', 'NS', 'MX', 'SRV'], true)) {
            $parts = preg_split('/\s+/', $value);
            $parts[count($parts) - 1] = strtolower(rtrim(end($parts), '.')).'.';

            return implode(' ', $parts);
        }

        return $value;
    }

    private function decodeTxt(string $value): string
    {
        return preg_replace_callback('/\\\\([0-9]{3}|[\x20-\x7e])/', function ($match): string {
            if (strlen($match[1]) === 3 && ctype_digit($match[1])) {
                if ((int) $match[1] > 255) {
                    $this->invalid('content', 'مقدار escape رکورد TXT معتبر نیست.');
                }

                return chr((int) $match[1]);
            }

            return $match[1];
        }, $value);
    }

    private function hostname(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
