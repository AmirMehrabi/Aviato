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
