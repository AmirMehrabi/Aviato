<?php

namespace Tests\Feature;

use App\Jobs\DeleteVirtualMachineJob;
use App\Models\CloudImage;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\ProjectMember;
use App\Models\ProxmoxServer;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\ProxmoxService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AdminVirtualMachineActionsTest extends TestCase
{
    use RefreshDatabase;

    private string $adminBaseUrl = 'https://admin.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'console.proxy_path' => '/console-ws',
            'portals.admin.domain' => 'admin.localhost',
            'portals.customer.domain' => 'cp.localhost',
        ]);
    }

    public function test_admin_can_open_vm_console_page(): void
    {
        $admin = User::factory()->create();
        $vm = $this->readyVm(Customer::factory()->create());

        $this->actingAs($admin, 'admin');

        $this->get($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid.'/console')
            ->assertOk()
            ->assertSee('کنسول مدیریتی ماشین')
            ->assertSee($vm->name);
    }

    public function test_admin_can_create_console_proxy_session(): void
    {
        $admin = User::factory()->create();
        $vm = $this->readyVm(Customer::factory()->create());

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('qemuConsoleSession')
                ->once()
                ->andReturn([
                    'port' => 5901,
                    'ticket' => 'PVEVNC:admin-ticket',
                ]);
        });

        $this->actingAs($admin, 'admin');

        $response = $this->postJson($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid.'/console/session')
            ->assertOk()
            ->assertJsonPath('password', 'PVEVNC:admin-ticket');

        $this->assertStringStartsWith('/console-ws/'.$vm->proxmox_server_id.'/nodes/pve1/qemu/101/vncwebsocket?', $response->json('websocket_url'));
    }

    public function test_admin_delete_queues_remote_delete_for_connected_vm(): void
    {
        Bus::fake();

        $admin = User::factory()->create();
        $vm = $this->readyVm(Customer::factory()->create());

        $this->actingAs($admin, 'admin');

        $this->delete($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid, [
            'delete_confirmation' => $vm->name,
        ])
            ->assertRedirect($this->adminBaseUrl.'/virtual-machines')
            ->assertSessionHas('status');

        $this->assertSame(VirtualMachine::STATUS_DELETING, $vm->fresh()->status);
        Bus::assertDispatched(DeleteVirtualMachineJob::class);
    }

    public function test_admin_delete_marks_panel_record_deleted_when_proxmox_connection_is_missing(): void
    {
        Bus::fake();

        $admin = User::factory()->create();
        $vm = $this->readyVm(Customer::factory()->create(), [
            'node' => null,
        ]);

        $this->actingAs($admin, 'admin');

        $this->delete($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid, [
            'delete_confirmation' => $vm->name,
        ])
            ->assertRedirect($this->adminBaseUrl.'/virtual-machines')
            ->assertSessionHas('status');

        $vm->refresh();

        $this->assertSame(VirtualMachine::STATUS_DELETED, $vm->status);
        $this->assertNull($vm->vmid);
        $this->assertNotNull($vm->deleted_at);
        $this->assertSame('local_finalize', data_get($vm->remote_state, 'delete_steps.0.step'));
        $this->assertSame('missing_proxmox_connection', data_get($vm->remote_state, 'delete_finalized_by'));
        Bus::assertNotDispatched(DeleteVirtualMachineJob::class);
    }

    public function test_admin_stop_shuts_down_remote_vm_before_marking_panel_stopped(): void
    {
        $admin = User::factory()->create();
        $vm = $this->readyVm(Customer::factory()->create());

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('shutdownVm')
                ->once()
                ->andReturn(['task_id' => 'UPID:pve1:shutdown']);
            $mock->shouldReceive('waitForTask')
                ->once()
                ->withArgs(fn ($server, string $node, string $taskId, int $timeout): bool => $node === 'pve1'
                    && $taskId === 'UPID:pve1:shutdown'
                    && $timeout === 180)
                ->andReturn(['status' => 'OK']);
            $mock->shouldReceive('waitForVmStopped')
                ->once()
                ->andReturn(['status' => 'stopped']);
        });

        $this->actingAs($admin, 'admin');

        $this->post($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid.'/stop', [
            'power_generation' => 0,
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $vm->refresh();

        $this->assertSame(VirtualMachine::STATUS_STOPPED, $vm->status);
        $this->assertNotNull($vm->last_stopped_at);
        $this->assertSame(1, data_get($vm->desired_state, 'power_generation'));
        $this->assertSame('admin_stop', data_get($vm->desired_state, 'power_intent_source'));
    }

    public function test_admin_stale_stop_request_cannot_override_a_newer_start_intent(): void
    {
        $admin = User::factory()->create();
        $vm = $this->readyVm(Customer::factory()->create(), [
            'desired_state' => [
                'status' => VirtualMachine::STATUS_RUNNING,
                'power_generation' => 4,
            ],
        ]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldNotReceive('shutdownVm');
        });

        $this->actingAs($admin, 'admin');

        $this->post($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid.'/stop', [
            'power_generation' => 3,
        ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $vm->refresh();

        $this->assertSame(VirtualMachine::STATUS_RUNNING, $vm->status);
        $this->assertSame(4, data_get($vm->desired_state, 'power_generation'));
    }

    public function test_admin_cannot_start_vm_when_billing_owner_wallet_is_negative(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('stopVm')
                ->once()
                ->andReturn(['task_id' => 'UPID:stop']);
            $mock->shouldNotReceive('startVm');
        });

        app(WalletService::class)->charge($customer, 1000, 'کسر آزمایشی');

        $this->actingAs($admin, 'admin');

        $this->post($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid.'/start')
            ->assertRedirect()
            ->assertSessionHas('error');

        $vm->refresh();
        $this->assertSame(VirtualMachine::STATUS_SUSPENDED, $vm->status);
    }

    public function test_admin_create_persists_selected_proxmox_node_storage_and_os_template(): void
    {
        Bus::fake();

        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $server = ProxmoxServer::create([
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
        $bundle = VmBundle::create([
            'name' => 'Starter',
            'slug' => 'starter-create',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'monthly_price' => 100000,
            'is_active' => true,
        ]);
        $image = CloudImage::create([
            'proxmox_server_id' => $server->id,
            'name' => 'Ubuntu 24.04',
            'slug' => 'ubuntu-2404-create-admin-test',
            'node' => 'pve1',
            'template_vmid' => 9000,
            'default_username' => 'ubuntu',
            'storage' => 'local-lvm',
            'disk_device' => 'scsi0',
            'network_bridge' => 'vmbr1',
            'ostype' => 'l26',
            'min_cpu_cores' => 2,
            'min_ram_gb' => 4,
            'min_disk_gb' => 40,
            'is_active' => true,
            'cloud_init_enabled' => true,
        ]);
        $image->allowedBundles()->sync([$bundle->id]);

        IpPool::create([
            'proxmox_server_id' => $server->id,
            'name' => 'PVE2 public',
            'node' => 'pve2',
            'network_bridge' => 'vmbr1',
            'gateway' => '192.168.20.1',
            'prefix_length' => 24,
            'nameservers' => '1.1.1.1',
            'start_ip' => '192.168.20.50',
            'end_ip' => '192.168.20.50',
            'is_active' => true,
        ]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('assignedGuestIpAddresses')
                ->once()
                ->withArgs(fn ($server, ?string $node): bool => $node === 'pve2')
                ->andReturn([]);
        });

        $this->actingAs($admin, 'admin');

        $this->post($this->adminBaseUrl.'/virtual-machines', [
            'customer_id' => $customer->id,
            'proxmox_server_id' => $server->id,
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'admin-created-vps',
            'hostname' => 'admin-created-vps',
            'node' => 'pve2',
            'storage' => 'fast-zfs',
            'os_template' => 'Ubuntu 24.04 PVE2',
            'login_username' => 'ubuntu',
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $vm = VirtualMachine::query()->where('name', 'admin-created-vps')->firstOrFail();

        $this->assertSame('pve2', $vm->node);
        $this->assertSame('fast-zfs', $vm->storage);
        $this->assertSame('Ubuntu 24.04 PVE2', $vm->os_template);
        $this->assertSame('192.168.20.50', $vm->ip_address);
    }

    public function test_admin_can_change_vm_ip_and_push_cloud_init_network_config(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $bundle = VmBundle::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'monthly_price' => 100000,
            'is_active' => true,
        ]);
        $vm = $this->readyVm($customer, ['vm_bundle_id' => $bundle->id]);
        $image = CloudImage::create([
            'proxmox_server_id' => $vm->proxmox_server_id,
            'name' => 'Ubuntu 24.04',
            'slug' => 'ubuntu-2404-admin-test',
            'node' => 'pve1',
            'template_vmid' => 9000,
            'default_username' => 'ubuntu',
            'storage' => 'local-lvm',
            'disk_device' => 'scsi0',
            'network_bridge' => 'vmbr1',
            'ostype' => 'l26',
            'min_cpu_cores' => 2,
            'min_ram_gb' => 4,
            'min_disk_gb' => 40,
            'is_active' => true,
            'cloud_init_enabled' => true,
        ]);
        $vm->update(['cloud_image_id' => $image->id]);

        $oldPool = IpPool::create([
            'proxmox_server_id' => $vm->proxmox_server_id,
            'name' => 'Old public',
            'node' => 'pve1',
            'network_bridge' => 'vmbr1',
            'gateway' => '192.168.10.1',
            'prefix_length' => 24,
            'nameservers' => '1.1.1.1',
            'start_ip' => '192.168.10.50',
            'end_ip' => '192.168.10.50',
            'is_active' => true,
        ]);
        $oldAddress = IpAddress::create([
            'ip_pool_id' => $oldPool->id,
            'virtual_machine_id' => $vm->id,
            'address' => '192.168.10.50',
            'status' => IpAddress::STATUS_ASSIGNED,
            'reserved_at' => now(),
            'assigned_at' => now(),
        ]);
        $vm->update(['ip_address_id' => $oldAddress->id]);

        $newPool = IpPool::create([
            'proxmox_server_id' => $vm->proxmox_server_id,
            'name' => 'New public',
            'node' => 'pve1',
            'network_bridge' => 'vmbr1',
            'gateway' => '10.20.30.1',
            'prefix_length' => 24,
            'nameservers' => '8.8.8.8',
            'start_ip' => '10.20.30.10',
            'end_ip' => '10.20.30.10',
            'is_active' => true,
        ]);
        $newAddress = IpAddress::create([
            'ip_pool_id' => $newPool->id,
            'address' => '10.20.30.10',
            'status' => IpAddress::STATUS_AVAILABLE,
        ]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('setVmNetworkLinkState')
                ->twice()
                ->andReturn(['task_id' => 'UPID:pve1:link']);
            $mock->shouldReceive('configureCloudInit')
                ->once()
                ->withArgs(fn ($server, array $options): bool => $options['ipconfig0'] === 'ip=10.20.30.10/24,gw=10.20.30.1'
                    && $options['nameserver'] === '8.8.8.8'
                    && $options['node'] === 'pve1'
                    && $options['vmid'] === 101)
                ->andReturn(['task_id' => 'UPID:pve1:config']);
            $mock->shouldReceive('waitForTask')
                ->times(4)
                ->andReturn(['status' => 'OK']);
            $mock->shouldReceive('regenerateCloudInit')
                ->once()
                ->andReturn(['task_id' => 'UPID:pve1:cloudinit']);
            $mock->shouldReceive('applyVmIpAntiSpoofing')
                ->once()
                ->andReturn(['mac_address' => 'BC:24:11:AA:22:33', 'allowed_ip' => '10.20.30.10']);
        });

        $this->actingAs($admin, 'admin');

        $this->put($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid, [
            'customer_id' => $customer->id,
            'project_id' => $vm->project_id,
            'proxmox_server_id' => $vm->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'ip_pool_id' => $newPool->id,
            'ip_address_id' => $newAddress->id,
            'vmid' => $vm->vmid,
            'name' => $vm->name,
            'hostname' => $vm->hostname,
            'node' => $vm->node,
            'storage' => $vm->storage,
            'os_template' => $vm->os_template,
            'iso_volume' => $vm->iso_volume,
            'network_bridge' => $vm->network_bridge,
            'tax_exempt' => $vm->tax_exempt,
        ])
            ->assertRedirect($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid)
            ->assertSessionHas('status');

        $vm->refresh();
        $oldAddress->refresh();
        $newAddress->refresh();

        $this->assertSame('10.20.30.10', $vm->ip_address);
        $this->assertSame('vmbr1', $vm->network_bridge);
        $this->assertSame(IpAddress::STATUS_RELEASED, $oldAddress->status);
        $this->assertNull($oldAddress->virtual_machine_id);
        $this->assertSame(IpAddress::STATUS_ASSIGNED, $newAddress->status);
        $this->assertSame($vm->id, $newAddress->virtual_machine_id);
        $this->assertSame('10.20.30.10', data_get($vm->remote_state, 'cloudinit_network_ip'));
    }

    public function test_admin_can_cleanup_failed_deleting_record_when_proxmox_connection_is_missing(): void
    {
        Bus::fake();

        $admin = User::factory()->create();
        $vm = $this->readyVm(Customer::factory()->create(), [
            'node' => null,
            'status' => VirtualMachine::STATUS_DELETING,
            'delete_failed_at' => now(),
            'delete_error' => 'VM is missing Proxmox server, node, or VMID.',
        ]);

        $this->actingAs($admin, 'admin');

        $this->delete($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid, [
            'delete_confirmation' => $vm->name,
        ])
            ->assertRedirect($this->adminBaseUrl.'/virtual-machines')
            ->assertSessionHas('status');

        $vm->refresh();

        $this->assertSame(VirtualMachine::STATUS_DELETED, $vm->status);
        $this->assertNull($vm->delete_error);
        $this->assertNotNull($vm->deleted_at);
        Bus::assertNotDispatched(DeleteVirtualMachineJob::class);
    }

    public function test_admin_can_transfer_vm_to_specific_target_customer_workspace(): void
    {
        $admin = User::factory()->create();
        $sourceCustomer = Customer::factory()->create(['name' => 'Source Customer']);
        $targetCustomer = Customer::factory()->create(['name' => 'Target Customer']);
        $sourceProject = $sourceCustomer->ensureDefaultProject();
        $targetCustomer->ensureDefaultProject();
        $targetProject = $targetCustomer->ownedProjects()->create([
            'name' => 'Production Workspace',
            'slug' => 'production-workspace',
            'is_default' => false,
        ]);
        $targetProject->members()->create([
            'customer_id' => $targetCustomer->id,
            'role' => ProjectMember::ROLE_OWNER,
        ]);
        $vm = $this->readyVm($sourceCustomer, [
            'project_id' => $sourceProject->id,
            'unbilled_amount' => 0,
        ]);

        $this->actingAs($admin, 'admin');

        $this->post($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid.'/transfer', [
            'to_customer_id' => $targetCustomer->id,
            'to_project_id' => $targetProject->id,
            'notes' => 'Move to production workspace.',
            'confirm_transfer' => '1',
        ])
            ->assertRedirect($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid)
            ->assertSessionHas('status');

        $vm->refresh();

        $this->assertSame($targetCustomer->id, $vm->customer_id);
        $this->assertSame($targetProject->id, $vm->project_id);
        $this->assertDatabaseHas('vm_transfers', [
            'virtual_machine_id' => $vm->id,
            'from_customer_id' => $sourceCustomer->id,
            'to_customer_id' => $targetCustomer->id,
            'from_project_id' => $sourceProject->id,
            'to_project_id' => $targetProject->id,
            'initiated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_transfer_rejects_workspace_not_owned_by_target_customer(): void
    {
        $admin = User::factory()->create();
        $sourceCustomer = Customer::factory()->create();
        $targetCustomer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $targetCustomer->ensureDefaultProject();
        $otherProject = $otherCustomer->ensureDefaultProject();
        $vm = $this->readyVm($sourceCustomer);

        $this->actingAs($admin, 'admin');

        $this->post($this->adminBaseUrl.'/virtual-machines/'.$vm->uuid.'/transfer', [
            'to_customer_id' => $targetCustomer->id,
            'to_project_id' => $otherProject->id,
            'confirm_transfer' => '1',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('to_project_id');

        $this->assertSame($sourceCustomer->id, $vm->fresh()->customer_id);
        $this->assertDatabaseMissing('vm_transfers', [
            'virtual_machine_id' => $vm->id,
            'to_project_id' => $otherProject->id,
        ]);
    }

    private function readyVm(Customer $customer, array $overrides = []): VirtualMachine
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
            'verify_tls' => false,
            'is_active' => true,
            'maintenance_mode' => false,
        ]);

        return VirtualMachine::create(array_merge([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $server->id,
            'vmid' => 101,
            'name' => 'customer-vps-101',
            'hostname' => 'customer-vps-101',
            'node' => 'pve1',
            'storage' => 'local-lvm',
            'network_bridge' => 'vmbr1',
            'ip_address' => '192.168.10.50',
            'login_username' => 'ubuntu',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ], $overrides));
    }
}
