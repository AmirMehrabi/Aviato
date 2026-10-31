<?php

namespace App\Services\CloudDns;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PowerDnsClient
{
    public function request(string $node, string $method, string $path = '', array $data = []): Response
    {
        $config = config('cloud-dns.nodes.'.$node);
        if (! config('cloud-dns.enabled') || empty($config['key']) || ! str_starts_with($config['url'], 'https://')) {
            throw new RuntimeException('Cloud DNS management is not configured.');
        }
        $url = rtrim($config['url'], '/').'/api/v1/servers/localhost'.($path === '' ? '' : '/'.ltrim($path, '/'));
        $response = Http::withHeaders(['X-API-Key' => $config['key']])
            ->withOptions(['verify' => config('cloud-dns.ca_bundle') ?: true, 'allow_redirects' => false])
            ->acceptJson()->connectTimeout(3)->timeout(15)
            ->send($method, $url, $data === [] ? [] : ['json' => $data]);
        // Do not include provider bodies, headers or API credentials in user-facing errors.
        if (! $response->successful() && ! ($response->status() === 404 && in_array($method, ['GET', 'DELETE'], true))) {
            throw new RuntimeException('PowerDNS '.$node.' returned HTTP '.$response->status().'.');
        }

        return $response;
    }

    public function zone(string $node, string $name): ?array
    {
        $response = $this->request($node, 'GET', 'zones/'.rawurlencode($name));

        return $response->status() === 404 ? null : $response->json();
    }

    public function metadata(string $node, string $name, string $kind, array $content): void
    {
        $this->request($node, 'PUT', 'zones/'.rawurlencode($name).'/metadata/'.$kind, ['kind' => $kind, 'metadata' => $content]);
    }
}
