<?php

namespace Tests\Feature;

use App\Jobs\DeleteVirtualMachineJob;
use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
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
            ->assertSee('Admin VM Console')
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
            'network_bridge' => 'vmbr0',
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
