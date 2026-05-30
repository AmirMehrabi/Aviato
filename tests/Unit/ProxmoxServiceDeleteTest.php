<?php

namespace Tests\Unit;

use App\Models\ProxmoxServer;
use App\Services\ProxmoxService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxmoxServiceDeleteTest extends TestCase
{
    public function test_delete_vm_sends_purge_and_unreferenced_disk_cleanup_payload(): void
    {
        Http::fake([
            'https://pve.local:8006/api2/json/nodes/pve1/qemu/101' => Http::response(['data' => 'UPID:delete'], 200),
        ]);

        $result = app(ProxmoxService::class)->deleteVm($this->server(), 'pve1', 101);

        $this->assertSame('UPID:delete', $result['task_id']);
        $this->assertSame([
            'purge' => 1,
            'destroy-unreferenced-disks' => 1,
        ], $result['payload']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'DELETE'
                && $request->url() === 'https://pve.local:8006/api2/json/nodes/pve1/qemu/101'
                && (int) $request->data()['purge'] === 1
                && (int) $request->data()['destroy-unreferenced-disks'] === 1;
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
