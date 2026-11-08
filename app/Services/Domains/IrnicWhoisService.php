<?php

namespace App\Services\Domains;

use Illuminate\Contracts\Cache\LockTimeoutException;
use RuntimeException;

class IrnicWhoisService
{
    public function check(string $domain, bool $search = true): array
    {
        if (! preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.ir\z/', $domain)) {
            throw new UnsupportedDomainExtension;
        }

        $budget = app(DomainProviderBudget::class);
        $cache = $budget->store();
        $key = 'domains.irnic.v1.'.hash('sha256', $domain);
        if ($cached = $cache->get($key)) {
            return $cached;
        }
        try {
            return $cache->lock($key.'.lock', 20)->block(2, function () use ($cache, $key, $domain, $budget, $search): array {
                if ($cached = $cache->get($key)) {
                    return $cached;
                }
                $budget->reserve('irnic', $search);
                // Fixed registry host, strict domain syntax and bounded reads prevent arbitrary socket access.
                $socket = @stream_socket_client('tcp://whois.nic.ir:43', $errno, $error, 5);
                if (! $socket) {
                    throw new RuntimeException('IRNIC lookup unavailable.');
                }
                try {
                    stream_set_timeout($socket, 7);
                    if (fwrite($socket, $domain."\r\n") === false) {
                        throw new RuntimeException('IRNIC query could not be sent.');
                    }
                    $response = '';
                    $deadline = microtime(true) + 8;
                    while (! feof($socket) && strlen($response) < 32768) {
                        $chunk = fread($socket, 4096);
                        if ($chunk === false) {
                            throw new RuntimeException('Incomplete IRNIC response.');
                        }
                        $response .= $chunk;
                        if (stream_get_meta_data($socket)['timed_out'] || microtime(true) > $deadline) {
                            throw new RuntimeException('IRNIC lookup timed out.');
                        }
                    }
                    if (! feof($socket)) {
                        throw new RuntimeException('IRNIC response exceeds limit.');
                    }
                } finally {
                    fclose($socket);
                }

                $result = $this->parse($domain, $response);
                $cache->put($key, $result, 30);

                return $result;
            });
        } catch (LockTimeoutException) {
            throw new DomainProviderBusy(2);
        }
    }

    public function parse(string $domain, string $response): array
    {
        $registered = preg_match('/^domain:\s*'.preg_quote($domain, '/').'\s*$/mi', $response) === 1;
        $unregistered = preg_match('/^%ERROR:101:\s*no entries found\s*$/mi', $response) === 1;
        if (! str_contains($response, 'IRNIC Whois server') || (! $registered && ! $unregistered) || ($registered && $unregistered)) {
            throw new RuntimeException('IRNIC did not return a definitive domain result.');
        }

        return ['domain' => $domain, 'available' => ! $registered, 'premium' => false, 'preliminary' => true, 'checked_at' => now()->toIso8601String()];
    }
}
