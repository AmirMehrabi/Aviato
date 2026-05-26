<?php

namespace Tests\Feature;

use App\Jobs\ProvisionCloudVirtualMachine;
use App\Models\CloudImage;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\ProxmoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CloudVmProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_can_queue_cloud_vps_and_reserve_ip(): void
    {
        Bus::fake();

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('assignedGuestIpAddresses')->once()->andReturn([]);
        });

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000000]);
        [$image, $bundle] = $this->catalog();

        $this->actingAs($customer, 'customer');
        $this->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'customer-vps-101',
            'hostname' => 'customer-vps-101',
            'login_username' => 'ubuntu',
            'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestKey customer@example.com',
        ])->assertRedirect($this->customerBaseUrl.'/servers');

        $vm = VirtualMachine::query()->firstOrFail();
        $this->assertSame($customer->id, $vm->customer_id);
        $this->assertSame($image->id, $vm->cloud_image_id);
        $this->assertSame('192.168.10.50', $vm->ip_address);
        $this->assertSame(VirtualMachine::PROVISION_PENDING, $vm->provisioning_status);

        $this->assertDatabaseHas('ip_addresses', [
            'address' => '192.168.10.50',
            'virtual_machine_id' => $vm->id,
            'status' => IpAddress::STATUS_RESERVED,
        ]);

        Bus::assertDispatched(ProvisionCloudVirtualMachine::class);
    }

    public function test_remote_assigned_pool_ip_is_skipped_when_reserving(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000000]);
        [$image, $bundle] = $this->catalog('192.168.10.51');

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('assignedGuestIpAddresses')->once()->andReturn(['192.168.10.50']);
        });

        $this->actingAs($customer, 'customer');
        $this->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'customer-vps-102',
            'hostname' => 'customer-vps-102',
            'login_username' => 'ubuntu',
        ])->assertRedirect($this->customerBaseUrl.'/servers');

        $vm = VirtualMachine::query()->firstOrFail();
        $this->assertSame('192.168.10.51', $vm->ip_address);
        $this->assertDatabaseHas('ip_addresses', [
            'address' => '192.168.10.51',
            'virtual_machine_id' => $vm->id,
            'status' => IpAddress::STATUS_RESERVED,
        ]);
    }

    public function test_cloud_image_minimum_resources_are_enforced(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000000]);
        [$image] = $this->catalog();

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/create')->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'name' => 'too-small',
            'login_username' => 'ubuntu',
            'cpu_cores' => 1,
            'ram_gb' => 1,
            'disk_gb' => 10,
        ])->assertRedirect($this->customerBaseUrl.'/servers/create')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('virtual_machines', 0);
        Bus::assertNotDispatched(ProvisionCloudVirtualMachine::class);
    }

    public function test_customer_below_wallet_threshold_cannot_queue_cloud_vps(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 999999]);
        [$image, $bundle] = $this->catalog();

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/create')->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'blocked-vps',
            'hostname' => 'ubuntu-blocked-vps',
            'login_username' => 'ubuntu',
        ])->assertRedirect($this->customerBaseUrl.'/servers/create')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('virtual_machines', 0);
        $this->assertDatabaseCount('ip_addresses', 0);
        Bus::assertNotDispatched(ProvisionCloudVirtualMachine::class);
    }

    public function test_create_page_exposes_os_family_and_version_options(): void
    {
        $customer = Customer::factory()->create();
        [$image] = $this->catalog();

        $this->actingAs($customer, 'customer');
        $response = $this->get($this->customerBaseUrl.'/servers/create');

        $response->assertOk();
        $this->assertSame('ubuntu', $response->viewData('osFamilies')[0]['key']);
        $this->assertSame('Ubuntu', $response->viewData('osFamilies')[0]['label']);
        $this->assertTrue($response->viewData('cloudImages')->contains($image));
    }

    public function test_customer_can_poll_owned_server_statuses(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        [$image, $bundle] = $this->catalog();

        $owned = VirtualMachine::create([
            'customer_id' => $owner->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'name' => 'poll-owned',
            'hostname' => 'poll-owned',
            'node' => $image->node,
            'ip_address' => '192.168.10.50',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_STOPPED,
            'provisioning_status' => VirtualMachine::PROVISION_PENDING,
        ]);

        $foreign = VirtualMachine::create([
            'customer_id' => $other->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'name' => 'poll-foreign',
            'hostname' => 'poll-foreign',
            'node' => $image->node,
            'ip_address' => '192.168.10.51',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->actingAs($owner, 'customer');
        $this->getJson($this->customerBaseUrl.'/servers/statuses?ids[]='.$owned->id.'&ids[]='.$foreign->id)
            ->assertOk()
            ->assertJsonCount(1, 'servers')
            ->assertJsonPath('servers.0.id', $owned->id)
            ->assertJsonPath('servers.0.status_label', 'خاموش')
            ->assertJsonPath('servers.0.provisioning_pending', true);
    }

    /**
     * @return array{CloudImage, VmBundle}
     */
    private function catalog(string $poolEnd = '192.168.10.50'): array
    {
        $server = ProxmoxServer::create([
            'name' => 'THR Proxmox',
            'datacenter' => 'THR-1',
            'host' => 'pve.local',
            'port' => 8006,
            'realm' => 'pam',
            'username' => 'root',
            'api_token_id' => 'root@pam!panel',
            'api_token_secret' => 'secret',
            'is_active' => true,
            'maintenance_mode' => false,
        ]);

        $image = CloudImage::create([
            'proxmox_server_id' => $server->id,
            'name' => 'Ubuntu 24.04',
            'slug' => 'ubuntu-2404',
            'os_family' => 'ubuntu',
            'os_version' => '24.04 LTS',
            'logo_key' => 'ubuntu',
            'node' => 'pve1',
            'template_vmid' => 9000,
            'default_username' => 'ubuntu',
            'storage' => 'local-lvm',
            'disk_device' => 'scsi0',
            'network_bridge' => 'vmbr0',
            'ostype' => 'l26',
            'min_cpu_cores' => 2,
            'min_ram_gb' => 4,
            'min_disk_gb' => 40,
            'is_active' => true,
        ]);

        IpPool::create([
            'proxmox_server_id' => $server->id,
            'name' => 'THR public',
            'node' => 'pve1',
            'network_bridge' => 'vmbr0',
            'gateway' => '192.168.10.1',
            'prefix_length' => 24,
            'nameservers' => '1.1.1.1',
            'start_ip' => '192.168.10.50',
            'end_ip' => $poolEnd,
            'is_active' => true,
        ]);

        $bundle = VmBundle::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'monthly_price' => 790000,
            'is_active' => true,
        ]);

        return [$image, $bundle];
    }
}
