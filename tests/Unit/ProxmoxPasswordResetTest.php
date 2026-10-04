<?php

namespace Tests\Unit;

use App\Models\ProxmoxServer;
use App\Services\ProxmoxService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxmoxPasswordResetTest extends TestCase
{
    public function test_password_update_only_changes_cipassword_and_shutdown_does_not_force_stop(): void
    {
        Http::fake(['*' => Http::response(['data' => null])]);
        $server = new ProxmoxServer([
            'name' => 'PVE', 'host' => 'pve.local', 'port' => 8006, 'username' => 'root', 'realm' => 'pam',
            'api_token_id' => 'root@pam!panel', 'api_token_secret' => 'secret', 'verify_tls' => false,
        ]);
        $proxmox = app(ProxmoxService::class);
        $proxmox->setCloudInitPassword($server, 'pve1', 101, '$6$example');
        $proxmox->shutdownVm($server, 'pve1', 101, false, [], false);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/qemu/101/config') && $request->data() === ['cipassword' => '$6$example']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/status/shutdown') && $request->data() === ['timeout' => 60, 'forceStop' => 0]);
        Http::assertSentCount(2);
    }
}
