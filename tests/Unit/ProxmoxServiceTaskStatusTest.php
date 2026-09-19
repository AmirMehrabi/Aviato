<?php

namespace Tests\Unit;

use App\Models\ProxmoxServer;
use App\Services\ProxmoxService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ProxmoxServiceTaskStatusTest extends TestCase
{
    public function test_task_warnings_are_treated_as_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => ['status' => 'stopped', 'exitstatus' => " \nWARNINGS: 1\r\n"],
            ]),
        ]);

        $result = app(ProxmoxService::class)->waitForTask($this->server(), 'pve1', 'UPID:warning');

        $this->assertSame(" \nWARNINGS: 1\r\n", $result['exitstatus']);
    }

    public function test_real_task_errors_still_fail(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => ['status' => 'stopped', 'exitstatus' => 'storage does not exist'],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Proxmox task failed: storage does not exist');

        app(ProxmoxService::class)->waitForTask($this->server(), 'pve1', 'UPID:error');
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
