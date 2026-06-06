<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\ProxmoxServer;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Services\IpPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIpPoolManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $adminBaseUrl = 'https://admin.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portals.admin.domain' => 'admin.localhost',
            'portals.customer.domain' => 'cp.localhost',
        ]);
    }

    public function test_admin_create_page_shows_range_preview_and_save_redirects_to_edit(): void
    {
        $admin = User::factory()->create();
        $server = $this->server();

        $this->actingAs($admin, 'admin');

        $this->get($this->adminBaseUrl.'/ip-pools/create')
            ->assertOk()
            ->assertSee('Range Preview')
            ->assertSee('Preview only');

        $response = $this->post($this->adminBaseUrl.'/ip-pools', [
            'proxmox_server_id' => $server->id,
            'name' => 'THR public',
            'node' => 'pve1',
            'network_bridge' => 'vmbr0',
            'gateway' => '192.168.10.1',
            'prefix_length' => 24,
            'nameservers' => '1.1.1.1',
            'start_ip' => '192.168.10.50',
            'end_ip' => '192.168.10.52',
            'is_active' => 1,
        ]);

        $pool = IpPool::query()->firstOrFail();

        $response->assertRedirect($this->adminBaseUrl.'/ip-pools/'.$pool->id.'/edit')
            ->assertSessionHas('status');

        $this->assertDatabaseCount('ip_addresses', 3);
        $this->assertDatabaseHas('ip_addresses', [
            'ip_pool_id' => $pool->id,
            'address' => '192.168.10.50',
            'status' => IpAddress::STATUS_AVAILABLE,
        ]);
    }

    public function test_admin_edit_page_materializes_inventory_and_reserves_multiple_ips(): void
    {
        $admin = User::factory()->create();
        $pool = $this->pool('192.168.10.52');

        $this->actingAs($admin, 'admin');

        $this->get($this->adminBaseUrl.'/ip-pools/'.$pool->id.'/edit')
            ->assertOk()
            ->assertSee('Reserve selected')
            ->assertSee('Released');

        $addresses = IpAddress::query()
            ->where('ip_pool_id', $pool->id)
            ->orderBy('address')
            ->pluck('id', 'address');

        $this->post($this->adminBaseUrl.'/ip-pools/'.$pool->id.'/addresses/reserve', [
            'address_ids' => [
                $addresses['192.168.10.50'],
                $addresses['192.168.10.51'],
            ],
        ])
            ->assertRedirect($this->adminBaseUrl.'/ip-pools/'.$pool->id.'/edit')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('ip_addresses', [
            'id' => $addresses['192.168.10.50'],
            'status' => IpAddress::STATUS_RESERVED,
        ]);
        $this->assertDatabaseHas('ip_addresses', [
            'id' => $addresses['192.168.10.51'],
            'status' => IpAddress::STATUS_RESERVED,
        ]);
    }

    public function test_admin_can_reserve_a_released_ip_again(): void
    {
        $admin = User::factory()->create();
        $pool = $this->pool('192.168.10.50');
        $address = IpAddress::query()
            ->where('ip_pool_id', $pool->id)
            ->where('address', '192.168.10.50')
            ->firstOrFail();

        $address->forceFill([
            'status' => IpAddress::STATUS_RELEASED,
            'released_at' => now(),
        ])->save();

        $this->actingAs($admin, 'admin');

        $this->post($this->adminBaseUrl.'/ip-pools/'.$pool->id.'/addresses/'.$address->id.'/reserve')
            ->assertRedirect($this->adminBaseUrl.'/ip-pools/'.$pool->id.'/edit')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('ip_addresses', [
            'id' => $address->id,
            'status' => IpAddress::STATUS_RESERVED,
        ]);
        $this->assertNull($address->fresh()->virtual_machine_id);
    }

    public function test_admin_cannot_reserve_an_assigned_ip(): void
    {
        $admin = User::factory()->create();
        $pool = $this->pool('192.168.10.50');
        $vm = $this->vm($pool);
        $address = IpAddress::query()
            ->where('ip_pool_id', $pool->id)
            ->where('address', '192.168.10.50')
            ->firstOrFail();

        $address->forceFill([
            'virtual_machine_id' => $vm->id,
            'status' => IpAddress::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ])->save();

        $vm->forceFill([
            'ip_address_id' => $address->id,
            'ip_address' => $address->address,
        ])->save();

        $this->actingAs($admin, 'admin');

        $this->post($this->adminBaseUrl.'/ip-pools/'.$pool->id.'/addresses/'.$address->id.'/reserve')
            ->assertSessionHasErrors('reservation');

        $this->assertDatabaseHas('ip_addresses', [
            'id' => $address->id,
            'status' => IpAddress::STATUS_ASSIGNED,
        ]);
    }

    private function server(): ProxmoxServer
    {
        return ProxmoxServer::create([
            'name' => 'THR Proxmox',
            'datacenter' => 'THR-1',
            'host' => 'pve.local',
            'port' => 8006,
            'realm' => 'pam',
            'username' => 'root',
            'api_token_id' => 'root@pam!panel',
            'api_token_secret' => 'secret',
            'verify_tls' => false,
            'is_active' => true,
            'maintenance_mode' => false,
        ]);
    }

    private function pool(string $endIp): IpPool
    {
        $server = $this->server();

        $pool = IpPool::create([
            'proxmox_server_id' => $server->id,
            'name' => 'THR public',
            'node' => 'pve1',
            'network_bridge' => 'vmbr0',
            'gateway' => '192.168.10.1',
            'prefix_length' => 24,
            'nameservers' => '1.1.1.1',
            'start_ip' => '192.168.10.50',
            'end_ip' => $endIp,
            'is_active' => true,
        ]);

        app(IpPoolService::class)->ensurePoolAddresses($pool);

        return $pool->refresh();
    }

    private function vm(IpPool $pool): VirtualMachine
    {
        $customer = Customer::factory()->create();

        return VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $pool->proxmox_server_id,
            'vmid' => 101,
            'name' => 'customer-vps-101',
            'hostname' => 'customer-vps-101',
            'node' => 'pve1',
            'storage' => 'local-lvm',
            'network_bridge' => 'vmbr0',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);
    }
}
