<?php

namespace App\Services\CloudDns;

use App\Models\DnsZone;
use Illuminate\Validation\ValidationException;

class ZoneFile
{
    /** Parse a deliberately limited BIND zone format; unsupported syntax fails the entire import. */
    public function parse(string $zone, string $text): array
    {
        if (strlen($text) > 250000) {
            $this->error('فایل بیش از حد بزرگ است.');
        }
        $lines = $this->logicalLines($text);
        $ttl = 300;
        $owner = null;
        $result = [];
        foreach ($lines as $line) {
            preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|[^\s]+/', trim($line), $matches);
            $tokens = $matches[0];
            if ($tokens === []) {
                continue;
            }
            if (strtoupper($tokens[0]) === '$ORIGIN') {
                if (count($tokens) !== 2 || strtolower(rtrim($tokens[1], '.')) !== $zone) {
                    $this->error('ORIGIN باید با دامنه انتخاب‌شده یکسان باشد.');
                }

                continue;
            }
            if (strtoupper($tokens[0]) === '$TTL') {
                $ttl = $this->ttl($tokens[1] ?? '');

                continue;
            }
            if (str_starts_with($tokens[0], '$')) {
                $this->error('دستورهای INCLUDE و GENERATE پشتیبانی نمی‌شوند.');
            }
            if (! ctype_space($line[0])) {
                $owner = array_shift($tokens);
            }
            if ($owner === null) {
                $this->error('نام رکورد در اولین سطر لازم است.');
            }
            $recordTtl = $ttl;
            while ($tokens !== [] && (strtoupper($tokens[0]) === 'IN' || preg_match('/^\d+[smhdw]?$/iD', $tokens[0]))) {
                $token = array_shift($tokens);
                if (strtoupper($token) !== 'IN') {
                    $recordTtl = $this->ttl($token);
                }
            }
            $type = strtoupper(array_shift($tokens) ?? '');
            $name = $this->absolute($owner, $zone);
            if ($type === 'SOA' || ($type === 'NS' && $name === $zone.'.')) {
                if ($name !== $zone.'.') {
                    $this->error('رکورد SOA فقط در ریشه مجاز است.');
                }

                continue; // Aviato owns the SOA and apex NS records.
            }
            if (! in_array($type, RecordValidator::TYPES, true) || $tokens === []) {
                $this->error('نوع رکورد یا ساختار فایل پشتیبانی نمی‌شود: '.$type);
            }
            if (in_array($type, ['CNAME', 'NS', 'MX', 'SRV'], true)) {
                $tokens[count($tokens) - 1] = $this->absolute(end($tokens), $zone);
            }
            $key = $name.'|'.$type;
            if (isset($result[$key]) && $result[$key]['ttl'] !== $recordTtl) {
                $this->error('TTL همه مقدارهای یک مجموعه رکورد باید یکسان باشد.');
            }
            $result[$key] ??= ['name' => $name, 'type' => $type, 'ttl' => $recordTtl, 'content' => ''];
            $result[$key]['content'] .= ($result[$key]['content'] === '' ? '' : "\n").implode(' ', $tokens);
        }
        if ($result === []) {
            $this->error('هیچ رکورد قابل ورودی پیدا نشد.');
        }

        return array_values($result);
    }

    public function export(DnsZone $zone): string
    {
        $text = '; Aviato Cloud DNS — desired configuration, SOA and apex NS are managed by Aviato.'."\n".'$ORIGIN '.$zone->fqdn()."\n".'$TTL 300'."\n";
        foreach (config('cloud-dns.nameservers') as $ns) {
            $text .= '@ 3600 IN NS '.$ns.".\n";
        }
        foreach ($zone->recordSets()->orderBy('name')->orderBy('type')->get() as $record) {
            foreach ($record->records as $value) {
                $text .= $record->name.' '.$record->ttl.' IN '.$record->type.' '.$value."\n";
            }
        }

        return $text;
    }

    private function logicalLines(string $text): array
    {
        $lines = [];
        $line = '';
        $quoted = false;
        $comment = false;
        $escaped = false;
        $depth = 0;
        foreach (str_split(str_replace("\r", '', $text)."\n") as $char) {
            if ($comment && $char !== "\n") {
                continue;
            }
            if ($escaped) {
                $line .= $char;
                $escaped = false;

                continue;
            }
            if ($char === '\\') {
                $line .= $char;
                $escaped = true;

                continue;
            }
            if ($char === '"') {
                $quoted = ! $quoted;
            }
            if (! $quoted) {
                if ($char === ';') {
                    $comment = true;

                    continue;
                }
                if ($char === '(' || $char === ')') {
                    $depth += $char === '(' ? 1 : -1;
                    if ($depth < 0) {
                        $this->error('پرانتز نامعتبر است.');
                    }
                    $line .= ' ';

                    continue;
                }
            }
            if ($char === "\n") {
                $comment = false;
                if ($quoted) {
                    $this->error('نقل‌قول TXT بسته نشده است.');
                }
                if ($depth > 0) {
                    $line .= ' ';
                } else {
                    if (trim($line) !== '') {
                        $lines[] = $line;
                    }
                    $line = '';
                }

                continue;
            }
            $line .= $char;
        }
        if ($quoted || $depth !== 0) {
            $this->error('نقل‌قول یا پرانتز بسته نشده است.');
        }

        return $lines;
    }

    private function absolute(string $name, string $zone): string
    {
        return $name === '@' ? $zone.'.' : (str_ends_with($name, '.') ? strtolower($name) : strtolower($name).'.'.$zone.'.');
    }

    private function ttl(string $value): int
    {
        if (! preg_match('/^(\d+)([smhdw]?)$/iD', $value, $m)) {
            $this->error('TTL معتبر نیست.');
        }

        return (int) $m[1] * match (strtolower($m[2])) {
            'm' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800, default => 1
        };
    }

    private function error(string $message): never
    {
        throw ValidationException::withMessages(['zone_file' => $message]);
    }
}
