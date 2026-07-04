<?php

namespace Tests\Unit;

use App\Models\ProxmoxServer;
use App\Services\ProxmoxService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxmoxServiceStartTest extends TestCase
{
    public function test_start_vm_returns_success_when_the_vm_is_already_running(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/nodes/pve1/qemu/101/status/current') => Http::response([
                    'data' => [
                        'status' => 'running',
                    ],
                ]),
                default => Http::response(['data' => null], 200),
            };
        });

        $result = app(ProxmoxService::class)->startVm($this->server(), 'pve1', 101, [
            'source' => 'test',
        ]);

        $this->assertTrue($result['already_running']);
        $this->assertSame('running', $result['status']);
        $this->assertNull($result['task_id']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/101/status/current';
        });
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
