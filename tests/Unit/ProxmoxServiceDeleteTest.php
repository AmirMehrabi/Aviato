<?php

namespace Tests\Unit;

use App\Models\ProxmoxServer;
use App\Services\ProxmoxService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxmoxServiceDeleteTest extends TestCase
{
    public function test_delete_vm_sends_purge_and_unreferenced_disk_cleanup_parameters_without_body(): void
    {
        Http::fake([
            'https://pve.local:8006/api2/json/nodes/pve1/qemu/101*' => Http::response(['data' => 'UPID:delete'], 200),
        ]);

        $result = app(ProxmoxService::class)->deleteVm($this->server(), 'pve1', 101);

        $this->assertSame('UPID:delete', $result['task_id']);
        $this->assertSame([
            'purge' => 1,
            'destroy-unreferenced-disks' => 1,
        ], $result['payload']);

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'DELETE'
                && str_starts_with($request->url(), 'https://pve.local:8006/api2/json/nodes/pve1/qemu/101?')
                && $request->data() === []
                && (int) $query['purge'] === 1
                && (int) $query['destroy-unreferenced-disks'] === 1;
        });
    }

    public function test_shutdown_vm_uses_shutdown_endpoint_with_timeout_and_force_stop(): void
    {
        Http::fake([
            'https://pve.local:8006/api2/json/nodes/pve1/qemu/101/status/shutdown' => Http::response(['data' => 'UPID:shutdown'], 200),
        ]);

        $result = app(ProxmoxService::class)->shutdownVm($this->server(), 'pve1', 101, false);

        $this->assertSame('UPID:shutdown', $result['task_id']);
        $this->assertSame([
            'timeout' => 60,
            'forceStop' => 1,
        ], $result['payload']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/101/status/shutdown'
                && (int) $request->data()['timeout'] === 60
                && (int) $request->data()['forceStop'] === 1;
        });
    }

    public function test_vm_config_or_null_treats_missing_config_file_as_missing_vm(): void
    {
        Http::fake([
            'https://pve.local:8006/api2/json/nodes/pve1/qemu/101/config' => Http::response([
                'data' => null,
                'message' => "Configuration file 'nodes/pve1/qemu-server/101.conf' does not exist\n",
            ], 500),
        ]);

        $result = app(ProxmoxService::class)->vmConfigOrNull($this->server(), 'pve1', 101);

        $this->assertNull($result);
    }

    public function test_apply_vm_ip_anti_spoofing_syncs_vm_firewall_ipfilter(): void
    {
        $sent = [];

        Http::fake(function (Request $request) use (&$sent) {
            $sent[] = [
                'method' => $request->method(),
                'url' => $request->url(),
                'data' => $request->data(),
            ];

            if ($request->method() === 'GET' && str_ends_with($request->url(), '/nodes/pve1/qemu/123/config')) {
                return Http::response([
                    'data' => [
                        'net0' => 'virtio=BC:24:11:AA:22:33,bridge=vmbr1',
                    ],
                ]);
            }

            if ($request->method() === 'GET' && str_ends_with($request->url(), '/nodes/pve1/qemu/123/firewall/ipset/ipfilter-net0')) {
                $matches = collect($sent)
                    ->where('method', 'GET')
                    ->where('url', 'https://pve.local:8006/api2/json/nodes/pve1/qemu/123/firewall/ipset/ipfilter-net0')
                    ->count();

                if ($matches === 1) {
                    return Http::response(['message' => 'no such ipset'], 404);
                }

                return Http::response([
                    'data' => [
                        ['cidr' => '5.202.19.118'],
                    ],
                ]);
            }

            if ($request->method() === 'GET' && str_ends_with($request->url(), '/nodes/pve1/qemu/123/firewall/rules')) {
                return Http::response([
                    'data' => [
                        ['pos' => 4, 'type' => 'out', 'action' => 'DROP', 'comment' => 'Aviato anti-spoof: drop other source IPs'],
                        ['pos' => 3, 'type' => 'out', 'action' => 'ACCEPT', 'comment' => 'Aviato anti-spoof: allow assigned source IP'],
                    ],
                ]);
            }

            return Http::response(['data' => null], 200);
        });

        $result = app(ProxmoxService::class)->applyVmIpAntiSpoofing($this->server(), 'pve1', 123, '5.202.19.112');

        $this->assertSame('BC:24:11:AA:22:33', $result['mac_address']);
        $this->assertSame('5.202.19.112', $result['allowed_ip']);

        $this->assertTrue(collect($sent)->contains(fn (array $request): bool => $request['method'] === 'PUT'
            && $request['url'] === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/123/config'
            && ($request['data']['net0'] ?? null) === 'virtio=BC:24:11:AA:22:33,bridge=vmbr1,firewall=1'));

        $this->assertTrue(collect($sent)->contains(fn (array $request): bool => $request['method'] === 'PUT'
            && $request['url'] === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/123/firewall/options'
            && (int) ($request['data']['enable'] ?? 0) === 1
            && (int) ($request['data']['ipfilter'] ?? 0) === 1));

        $this->assertTrue(collect($sent)->contains(fn (array $request): bool => $request['method'] === 'POST'
            && $request['url'] === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/123/firewall/ipset'
            && ($request['data']['name'] ?? null) === 'ipfilter-net0'));

        $this->assertTrue(collect($sent)->contains(fn (array $request): bool => $request['method'] === 'DELETE'
            && $request['url'] === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/123/firewall/ipset/ipfilter-net0/5.202.19.118'));

        $this->assertTrue(collect($sent)->contains(fn (array $request): bool => $request['method'] === 'POST'
            && $request['url'] === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/123/firewall/ipset/ipfilter-net0'
            && ($request['data']['cidr'] ?? null) === '5.202.19.112'));

        $this->assertTrue(collect($sent)->contains(fn (array $request): bool => $request['method'] === 'DELETE'
            && $request['url'] === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/123/firewall/rules/4'));

        $this->assertTrue(collect($sent)->contains(fn (array $request): bool => $request['method'] === 'DELETE'
            && $request['url'] === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/123/firewall/rules/3'));

        $this->assertTrue(collect($sent)->contains(fn (array $request): bool => $request['method'] === 'POST'
            && $request['url'] === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/123/firewall/rules'
            && ($request['data']['type'] ?? null) === 'out'
            && ($request['data']['action'] ?? null) === 'ACCEPT'
            && ($request['data']['iface'] ?? null) === 'net0'
            && ($request['data']['source'] ?? null) === '5.202.19.112'
            && ($request['data']['comment'] ?? null) === 'Aviato anti-spoof: allow assigned source IP'));

        $this->assertTrue(collect($sent)->contains(fn (array $request): bool => $request['method'] === 'POST'
            && $request['url'] === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/123/firewall/rules'
            && ($request['data']['type'] ?? null) === 'out'
            && ($request['data']['action'] ?? null) === 'DROP'
            && ($request['data']['iface'] ?? null) === 'net0'
            && ($request['data']['comment'] ?? null) === 'Aviato anti-spoof: drop other source IPs'));
    }

    public function test_apply_vm_ip_anti_spoofing_keeps_ipset_when_visible_rule_sync_fails(): void
    {
        $sent = [];

        Http::fake(function (Request $request) use (&$sent) {
            $sent[] = [
                'method' => $request->method(),
                'url' => $request->url(),
                'data' => $request->data(),
            ];

            if ($request->method() === 'GET' && str_ends_with($request->url(), '/nodes/pve1/qemu/123/config')) {
                return Http::response([
                    'data' => [
                        'net0' => 'virtio=BC:24:11:AA:22:33,bridge=vmbr1',
                    ],
                ]);
            }

            if ($request->method() === 'GET' && str_ends_with($request->url(), '/nodes/pve1/qemu/123/firewall/ipset/ipfilter-net0')) {
                return Http::response(['data' => []]);
            }

            if ($request->method() === 'GET' && str_ends_with($request->url(), '/nodes/pve1/qemu/123/firewall/rules')) {
                return Http::response(['data' => []]);
            }

            if ($request->method() === 'POST' && str_ends_with($request->url(), '/nodes/pve1/qemu/123/firewall/rules')) {
                return Http::response(['errors' => ['source' => 'parameter verification failed']], 400);
            }

            return Http::response(['data' => null], 200);
        });

        $result = app(ProxmoxService::class)->applyVmIpAntiSpoofing($this->server(), 'pve1', 123, '5.202.19.112');

        $this->assertSame('5.202.19.112', $result['allowed_ip']);
        $this->assertArrayHasKey('error', $result['rules']);

        $this->assertTrue(collect($sent)->contains(fn (array $request): bool => $request['method'] === 'PUT'
            && $request['url'] === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/123/firewall/options'
            && (int) ($request['data']['enable'] ?? 0) === 1
            && (int) ($request['data']['ipfilter'] ?? 0) === 1));

        $this->assertTrue(collect($sent)->contains(fn (array $request): bool => $request['method'] === 'POST'
            && $request['url'] === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/123/firewall/ipset/ipfilter-net0'
            && ($request['data']['cidr'] ?? null) === '5.202.19.112'));
    }

    public function test_delete_vm_retries_without_unreferenced_disk_cleanup_when_proxmox_rejects_option(): void
    {
        Http::fakeSequence()
            ->push(['errors' => ['destroy-unreferenced-disks' => 'unknown option']], 400)
            ->push(['data' => 'UPID:delete'], 200);

        $result = app(ProxmoxService::class)->deleteVm($this->server(), 'pve1', 101);

        $this->assertSame('UPID:delete', $result['task_id']);
        $this->assertSame(['purge' => 1], $result['payload']);
        Http::assertSentCount(2);
    }

    private function server(): ProxmoxServer
    {
        return new ProxmoxServer([
            'name' => 'THR Proxmox',
            'host' => 'pve.local',
            'port' => 8006,
            'realm' => 'pam',
            'username' => 'root',
            'api_token_id' => 'panel',
            'api_token_secret' => 'secret',
            'verify_tls' => false,
        ]);
    }
}
