<?php

namespace Tests\Unit;

use App\Models\ProxmoxServer;
use App\Services\ProxmoxService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxmoxServiceStorageInventoryTest extends TestCase
{
    public function test_storage_is_queried_and_mapped_to_its_configured_node(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/nodes') => Http::response(['data' => [
                    ['node' => 'srv1', 'status' => 'online'],
                ]]),
                str_ends_with($path, '/nodes/srv1/storage') => Http::response(['data' => [
                    ['storage' => 'local-lvm', 'type' => 'lvmthin', 'content' => 'images,rootdir', 'active' => 1, 'used' => 10, 'total' => 100],
                    ['storage' => 'SSD-SAS', 'type' => 'zfspool', 'content' => 'images,rootdir', 'active' => 0],
                    ['storage' => 'SSD-SATA', 'type' => 'zfspool', 'content' => 'images,rootdir', 'active' => 0],
                ]]),
                str_ends_with($path, '/nodes/srv2/storage') => Http::response(['data' => [
                    ['storage' => 'SSD-SAS', 'type' => 'zfspool', 'content' => 'images,rootdir', 'active' => 1, 'used' => 25, 'total' => 100],
                    ['storage' => 'SSD-SATA', 'type' => 'zfspool', 'content' => 'images,rootdir', 'active' => 1, 'used' => 40, 'total' => 100],
                ]]),
                str_ends_with($path, '/storage') => Http::response(['data' => [
                    ['storage' => 'local-lvm', 'type' => 'lvmthin', 'content' => 'images,rootdir'],
                    ['storage' => 'SSD-SAS', 'type' => 'zfspool', 'content' => 'images,rootdir', 'nodes' => 'srv2'],
                    ['storage' => 'SSD-SATA', 'type' => 'zfspool', 'content' => 'images,rootdir', 'nodes' => 'srv2'],
                ]]),
                default => Http::response(['data' => []]),
            };
        });

        $storage = collect(app(ProxmoxService::class)->summary($this->server())['storage']);

        $this->assertSame('srv1', $storage->firstWhere('storage', 'local-lvm')['node']);
        $this->assertSame('srv2', $storage->firstWhere('storage', 'SSD-SAS')['node']);
        $this->assertSame(1, $storage->firstWhere('storage', 'SSD-SAS')['active']);
        $this->assertSame(25, $storage->firstWhere('storage', 'SSD-SAS')['used']);
        $this->assertSame('srv2', $storage->firstWhere('storage', 'SSD-SATA')['node']);
        $this->assertFalse($storage->contains(
            fn (array $item): bool => in_array($item['storage'], ['SSD-SAS', 'SSD-SATA'], true)
                && $item['node'] === 'srv1'
        ));

        Http::assertSent(fn (Request $request): bool => str_ends_with(
            parse_url($request->url(), PHP_URL_PATH),
            '/nodes/srv2/storage'
        ));
    }

    public function test_node_storage_request_uses_its_dedicated_api_endpoint(): void
    {
        Http::fake(function (Request $request) {
            $host = parse_url($request->url(), PHP_URL_HOST);
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/nodes') => Http::response(['data' => [
                    ['node' => 'srv1', 'status' => 'online'],
                ]]),
                str_ends_with($path, '/nodes/srv1/storage') => Http::response(['data' => []]),
                $host === '10.0.0.12' && str_ends_with($path, '/nodes/srv2/storage') => Http::response(['data' => [
                    ['storage' => 'SSD-SAS', 'type' => 'zfspool', 'content' => 'images,rootdir', 'active' => 1, 'used' => 25, 'total' => 100],
                ]]),
                $host === 'pve.local' && str_ends_with($path, '/nodes/srv2/storage') => Http::response([
                    'data' => null,
                    'message' => 'proxy connection failed',
                ], 595),
                str_ends_with($path, '/storage') => Http::response(['data' => [
                    ['storage' => 'SSD-SAS', 'type' => 'zfspool', 'content' => 'images,rootdir', 'nodes' => 'srv2'],
                ]]),
                default => Http::response(['data' => []]),
            };
        });

        $storage = collect(app(ProxmoxService::class)->summary(
            $this->server(
                ['srv2' => 'https://10.0.0.12:8006'],
                ['srv2' => ['token_id' => 'root@pam!srv2', 'token_secret' => 'srv2-secret']]
            )
        )['storage']);

        $this->assertSame('srv2', $storage->firstWhere('storage', 'SSD-SAS')['node']);
        $this->assertSame(1, $storage->firstWhere('storage', 'SSD-SAS')['active']);
        Http::assertNotSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_HOST) === 'pve.local'
            && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/nodes/srv2/storage'));
        Http::assertSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_HOST) === '10.0.0.12'
            && $request->header('Authorization')[0] === 'PVEAPIToken=root@pam!srv2=srv2-secret');
    }

    /**
     * @param  array<array-key, string>  $apiEndpoints
     * @param  array<string, array{token_id: string, token_secret: string}>  $nodeCredentials
     */
    private function server(array $apiEndpoints = [], array $nodeCredentials = []): ProxmoxServer
    {
        return new ProxmoxServer([
            'name' => 'Cluster',
            'datacenter' => 'THR-1',
            'host' => 'pve.local',
            'port' => 8006,
            'realm' => 'pam',
            'username' => 'root',
            'api_token_id' => 'root@pam!panel',
            'api_token_secret' => 'secret',
            'verify_tls' => false,
            'api_endpoints' => $apiEndpoints,
            'node_api_credentials' => $nodeCredentials,
        ]);
    }
}
