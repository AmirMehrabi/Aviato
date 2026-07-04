<?php

namespace Tests\Feature;

use App\Models\ProxmoxServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxmoxNodeEndpointManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $adminBaseUrl = 'https://admin.localhost';

    public function test_admin_can_assign_dedicated_api_address_to_each_discovered_node(): void
    {
        $admin = User::factory()->create();
        $server = $this->server();

        $this->actingAs($admin, 'admin');
        $this->get($this->adminBaseUrl.'/proxmox-servers/'.$server->id.'/edit')
            ->assertOk()
            ->assertSee('node_api_endpoints[srv1]', false)
            ->assertSee('node_api_endpoints[srv2]', false);

        $this->put($this->adminBaseUrl.'/proxmox-servers/'.$server->id, [
            'name' => $server->name,
            'cluster_name' => $server->cluster_name,
            'datacenter' => $server->datacenter,
            'environment' => $server->environment,
            'host' => $server->host,
            'port' => $server->port,
            'realm' => $server->realm,
            'username' => $server->username,
            'verify_tls' => 0,
            'is_active' => 1,
            'maintenance_mode' => 0,
            'cpu_threshold_percent' => 80,
            'ram_threshold_percent' => 85,
            'disk_threshold_percent' => 80,
            'node_api_endpoints' => [
                'srv1' => 'https://172.19.19.2:8006',
                'srv2' => 'https://172.19.19.3:8006',
            ],
        ])->assertRedirect($this->adminBaseUrl.'/proxmox-servers/'.$server->id);

        $this->assertSame([
            'srv1' => 'https://172.19.19.2:8006',
            'srv2' => 'https://172.19.19.3:8006',
        ], $server->refresh()->api_endpoints);
    }

    private function server(): ProxmoxServer
    {
        return ProxmoxServer::create([
            'name' => 'THR Cluster',
            'cluster_name' => 'production',
            'datacenter' => 'THR-1',
            'environment' => 'production',
            'host' => '172.19.19.2',
            'port' => 8006,
            'realm' => 'pam',
            'username' => 'root',
            'api_token_id' => 'root@pam!panel',
            'api_token_secret' => 'secret',
            'verify_tls' => false,
            'is_active' => true,
            'maintenance_mode' => false,
            'remote_inventory' => [
                'nodes' => [
                    ['node' => 'srv1', 'status' => 'online'],
                    ['node' => 'srv2', 'status' => 'online'],
                ],
            ],
        ]);
    }
}
