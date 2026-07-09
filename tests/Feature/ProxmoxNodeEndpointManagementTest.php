<?php

namespace Tests\Feature;

use App\Models\ProxmoxServer;
use App\Models\User;
use App\Services\ProxmoxService;
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
            'node_api_credentials' => [
                'srv1' => ['token_id' => 'root@pam!srv1', 'token_secret' => 'srv1-secret'],
                'srv2' => ['token_id' => 'root@pam!srv2', 'token_secret' => 'srv2-secret'],
            ],
        ])->assertRedirect($this->adminBaseUrl.'/proxmox-servers/'.$server->id);

        $this->assertSame([
            'srv1' => 'https://172.19.19.2:8006',
            'srv2' => 'https://172.19.19.3:8006',
        ], $server->refresh()->api_endpoints);
        $this->assertSame('root@pam!srv2', $server->node_api_credentials['srv2']['token_id']);
        $this->assertSame('srv2-secret', $server->node_api_credentials['srv2']['token_secret']);
        $this->assertStringNotContainsString(
            'srv2-secret',
            (string) $server->getRawOriginal('node_api_credentials')
        );
    }

    public function test_opening_server_page_does_not_trigger_or_persist_a_live_sync(): void
    {
        $admin = User::factory()->create();
        $server = $this->server();
        $savedInventory = $server->remote_inventory;

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldNotReceive('syncDesiredState');
        });

        $this->actingAs($admin, 'admin');
        $this->get($this->adminBaseUrl.'/proxmox-servers/'.$server->id)
            ->assertOk()
            ->assertSee('srv1')
            ->assertSee('srv2');

        $this->assertSame($savedInventory, $server->refresh()->remote_inventory);
    }

    public function test_configured_node_missing_from_latest_inventory_is_shown_as_stale_not_active(): void
    {
        $admin = User::factory()->create();
        $server = $this->serverWithRemovedNode();

        $this->actingAs($admin, 'admin');
        $this->get($this->adminBaseUrl.'/proxmox-servers/'.$server->id.'/edit')
            ->assertOk()
            ->assertSee('node_api_endpoints[srv2]', false)
            ->assertDontSee('node_api_endpoints[srv1]', false)
            ->assertSee('nodeهای حذف‌شده از آخرین Sync')
            ->assertSee('remove_stale_nodes[]', false)
            ->assertSee('srv1');
    }

    public function test_normal_save_preserves_stale_node_configuration_until_explicit_removal(): void
    {
        $admin = User::factory()->create();
        $server = $this->serverWithRemovedNode();

        $this->actingAs($admin, 'admin');
        $this->put($this->adminBaseUrl.'/proxmox-servers/'.$server->id, $this->payload($server, [
            'node_api_endpoints' => [
                'srv2' => 'https://172.19.19.3:8006',
            ],
            'node_api_credentials' => [
                'srv2' => ['token_id' => 'root@pam!srv2', 'token_secret' => ''],
            ],
        ]))->assertRedirect($this->adminBaseUrl.'/proxmox-servers/'.$server->id);

        $server->refresh();

        $this->assertSame('https://172.19.19.2:8006', $server->api_endpoints['srv1']);
        $this->assertSame('root@pam!srv1', $server->node_api_credentials['srv1']['token_id']);
        $this->assertSame('srv1-secret', $server->node_api_credentials['srv1']['token_secret']);
    }

    public function test_admin_can_explicitly_remove_stale_node_configuration(): void
    {
        $admin = User::factory()->create();
        $server = $this->serverWithRemovedNode();

        $this->actingAs($admin, 'admin');
        $this->put($this->adminBaseUrl.'/proxmox-servers/'.$server->id, $this->payload($server, [
            'node_api_endpoints' => [
                'srv2' => 'https://172.19.19.3:8006',
            ],
            'node_api_credentials' => [
                'srv2' => ['token_id' => 'root@pam!srv2', 'token_secret' => ''],
            ],
            'remove_stale_nodes' => ['srv1'],
        ]))->assertRedirect($this->adminBaseUrl.'/proxmox-servers/'.$server->id);

        $server->refresh();

        $this->assertArrayNotHasKey('srv1', $server->api_endpoints);
        $this->assertArrayNotHasKey('srv1', $server->node_api_credentials);
        $this->assertSame('https://172.19.19.3:8006', $server->api_endpoints['srv2']);
        $this->assertSame('srv2-secret', $server->node_api_credentials['srv2']['token_secret']);
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

    private function serverWithRemovedNode(): ProxmoxServer
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
            'api_endpoints' => [
                'srv1' => 'https://172.19.19.2:8006',
                'srv2' => 'https://172.19.19.3:8006',
            ],
            'node_api_credentials' => [
                'srv1' => ['token_id' => 'root@pam!srv1', 'token_secret' => 'srv1-secret'],
                'srv2' => ['token_id' => 'root@pam!srv2', 'token_secret' => 'srv2-secret'],
            ],
            'remote_inventory' => [
                'nodes' => [
                    ['node' => 'srv2', 'status' => 'online'],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(ProxmoxServer $server, array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }
}
