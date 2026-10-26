<?php

namespace App\Services\Domains;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class IrnicWhoisService
{
    public function check(string $domain): array
    {
        if (! preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.ir\z/', $domain)) {
            throw new UnsupportedDomainExtension;
        }

        return Cache::remember('domains.irnic.v1.'.hash('sha256', $domain), 30, function () use ($domain): array {
            if (RateLimiter::tooManyAttempts('domains.irnic.registry', 30)) {
                throw new RuntimeException('IRNIC lookup rate limit reached.');
            }
            RateLimiter::hit('domains.irnic.registry', 60);
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
                while (! feof($socket) && strlen($response) < 32768) {
                    $chunk = fread($socket, 4096);
                    if ($chunk === false) {
                        throw new RuntimeException('Incomplete IRNIC response.');
                    }
                    $response .= $chunk;
                    if (stream_get_meta_data($socket)['timed_out']) {
                        throw new RuntimeException('IRNIC lookup timed out.');
                    }
                }
                if (! feof($socket)) {
                    throw new RuntimeException('IRNIC response exceeds limit.');
                }
            } finally {
                fclose($socket);
            }

            return $this->parse($domain, $response);
        });
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
