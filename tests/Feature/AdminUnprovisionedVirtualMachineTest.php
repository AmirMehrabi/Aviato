<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\ProxmoxServer;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\ProxmoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUnprovisionedVirtualMachineTest extends TestCase
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

    public function test_admin_can_see_remote_guests_that_are_not_panel_vms(): void
    {
        $admin = User::factory()->create();
        $server = $this->server([
            'virtual_machines' => [
                ['type' => 'qemu', 'vmid' => 101, 'name' => 'unclaimed-qemu', 'node' => 'pve1', 'status' => 'running', 'maxcpu' => 2, 'maxmem' => 4 * 1073741824, 'maxdisk' => 40 * 1073741824],
                ['type' => 'lxc', 'vmid' => 102, 'name' => 'unclaimed-lxc', 'node' => 'pve1', 'status' => 'stopped', 'maxcpu' => 1, 'maxmem' => 2 * 1073741824, 'maxdisk' => 20 * 1073741824],
            ],
        ]);
        VirtualMachine::create([
            'customer_id' => Customer::factory()->create()->id,
            'proxmox_server_id' => $server->id,
            'vmid' => 103,
            'name' => 'already-claimed',
            'cpu_cores' => 1,
            'ram_gb' => 1,
            'disk_gb' => 10,
        ]);

        $this->actingAs($admin, 'admin')
            ->get($this->adminBaseUrl.'/unprovisioned-virtual-machines')
            ->assertOk()
            ->assertSee('unclaimed-qemu')
            ->assertSee('unclaimed-lxc')
            ->assertDontSee('already-claimed');
    }

    public function test_admin_can_claim_a_guest_with_bundle_and_ip(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $server = $this->server();
        $bundle = VmBundle::create([
            'name' => 'Import bundle', 'slug' => 'import-bundle', 'cpu_cores' => 4,
            'ram_gb' => 8, 'disk_gb' => 80, 'ip_count' => 1, 'monthly_price' => 100000,
            'is_active' => true,
        ]);
        $pool = IpPool::create([
            'proxmox_server_id' => $server->id, 'name' => 'Import pool', 'node' => 'pve1',
            'gateway' => '192.0.2.1', 'start_ip' => '192.0.2.10', 'end_ip' => '192.0.2.10',
            'is_active' => true,
        ]);
        $ip = IpAddress::create(['ip_pool_id' => $pool->id, 'address' => '192.0.2.10', 'status' => IpAddress::STATUS_AVAILABLE]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('summary')->once()->andReturn([
                'virtual_machines' => [['type' => 'lxc', 'vmid' => 101, 'name' => 'import-me', 'node' => 'pve1', 'status' => 'running', 'maxcpu' => 2, 'maxmem' => 4 * 1073741824, 'maxdisk' => 40 * 1073741824]],
            ]);
        });

        $this->actingAs($admin, 'admin')
            ->post($this->adminBaseUrl.'/unprovisioned-virtual-machines/claim', [
                'proxmox_server_id' => $server->id,
                'vmid' => 101,
                'customer_id' => $customer->id,
                'vm_bundle_id' => $bundle->id,
                'ip_address_id' => $ip->id,
            ])
            ->assertRedirect();

        $vm = VirtualMachine::query()->where('proxmox_server_id', $server->id)->where('vmid', 101)->firstOrFail();
        $this->assertSame($customer->id, $vm->customer_id);
        $this->assertSame($customer->ensureDefaultProject()->id, $vm->project_id);
        $this->assertSame(VirtualMachine::PROVISION_READY, $vm->provisioning_status);
        $this->assertSame('lxc', data_get($vm->provider_metadata, 'guest_type'));
        $this->assertSame($ip->id, $vm->ip_address_id);
        $this->assertSame(IpAddress::STATUS_ASSIGNED, $ip->fresh()->status);
    }

    public function test_claim_rejects_a_bundle_that_is_too_small(): void
    {
        $admin = User::factory()->create();
        $server = $this->server();
        $bundle = VmBundle::create([
            'name' => 'Small bundle', 'slug' => 'small-import-bundle', 'cpu_cores' => 1,
            'ram_gb' => 1, 'disk_gb' => 10, 'ip_count' => 1, 'monthly_price' => 100000,
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create();
        $pool = IpPool::create(['proxmox_server_id' => $server->id, 'name' => 'Pool', 'node' => 'pve1', 'gateway' => '192.0.2.1', 'start_ip' => '192.0.2.1', 'end_ip' => '192.0.2.2', 'is_active' => true]);
        $ip = IpAddress::create(['ip_pool_id' => $pool->id, 'address' => '192.0.2.2', 'status' => IpAddress::STATUS_AVAILABLE]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('summary')->once()->andReturn([
                'virtual_machines' => [['type' => 'qemu', 'vmid' => 101, 'name' => 'large-guest', 'node' => 'pve1', 'status' => 'stopped', 'maxcpu' => 2, 'maxmem' => 4 * 1073741824, 'maxdisk' => 40 * 1073741824]],
            ]);
        });

        $this->actingAs($admin, 'admin')
            ->post($this->adminBaseUrl.'/unprovisioned-virtual-machines/claim', [
                'proxmox_server_id' => $server->id, 'vmid' => 101, 'customer_id' => $customer->id,
                'vm_bundle_id' => $bundle->id, 'ip_address_id' => $ip->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('virtual_machines', ['proxmox_server_id' => $server->id, 'vmid' => 101]);
        $this->assertSame(IpAddress::STATUS_AVAILABLE, $ip->fresh()->status);
    }

    private function server(array $inventory = []): ProxmoxServer
    {
        return ProxmoxServer::create([
            'name' => 'Import Proxmox', 'datacenter' => 'THR-1', 'host' => 'pve.local',
            'port' => 8006, 'realm' => 'pam', 'username' => 'root', 'api_token_id' => 'panel',
            'api_token_secret' => 'secret', 'verify_tls' => false, 'is_active' => true,
            'maintenance_mode' => false, 'remote_inventory' => array_merge([
                'virtual_machines' => [['type' => 'qemu', 'vmid' => 101, 'name' => 'import-me', 'node' => 'pve1', 'status' => 'running', 'maxcpu' => 2, 'maxmem' => 4 * 1073741824, 'maxdisk' => 40 * 1073741824]],
            ], $inventory),
        ]);
    }
}
