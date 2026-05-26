<?php

namespace Tests\Feature;

use App\Jobs\DeleteVirtualMachineJob;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class CustomerServerDeletionTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_delete_queues_vm_deletion_without_waiting_for_proxmox(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);

        $this->actingAs($customer, 'customer');
        $this->delete($this->customerBaseUrl.'/servers/'.$vm->id)
            ->assertRedirect($this->customerBaseUrl.'/servers')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('virtual_machines', [
            'id' => $vm->id,
            'status' => VirtualMachine::STATUS_DELETING,
            'delete_error' => null,
        ]);
        $this->assertNotNull($vm->fresh()->delete_requested_at);
        Bus::assertDispatched(DeleteVirtualMachineJob::class);
    }

    public function test_customer_cannot_queue_duplicate_delete_for_locked_vm(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, ['status' => VirtualMachine::STATUS_DELETING, 'delete_requested_at' => now()]);

        $this->actingAs($customer, 'customer');
        $this->delete($this->customerBaseUrl.'/servers/'.$vm->id)
            ->assertRedirect($this->customerBaseUrl.'/servers')
            ->assertSessionHas('status');

        Bus::assertNotDispatched(DeleteVirtualMachineJob::class);
    }

    public function test_deleting_status_keeps_customer_polling_active(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, ['status' => VirtualMachine::STATUS_DELETING]);

        $this->actingAs($customer, 'customer');
        $this->getJson($this->customerBaseUrl.'/servers/statuses?ids[]='.$vm->id)
            ->assertOk()
            ->assertJsonPath('servers.0.status_label', 'در حال حذف')
            ->assertJsonPath('servers.0.action_pending', true)
            ->assertJsonPath('servers.0.is_deleting', true);
    }

    public function test_delete_job_shuts_down_deletes_and_releases_ip(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, ['status' => VirtualMachine::STATUS_DELETING]);
        $address = $this->assignedAddress($vm);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'running']);
            $mock->shouldReceive('shutdownVm')->once()->andReturn(['task_id' => 'UPID:shutdown']);
            $mock->shouldReceive('waitForTask')->once()->with(Mockery::any(), 'pve1', 'UPID:shutdown', 180)->andReturn(['exitstatus' => 'OK']);
            $mock->shouldReceive('deleteVm')->once()->andReturn(['task_id' => 'UPID:delete']);
            $mock->shouldReceive('waitForTask')->once()->with(Mockery::any(), 'pve1', 'UPID:delete', 300)->andReturn(['exitstatus' => 'OK']);
        });

        app(DeleteVirtualMachineJob::class, ['virtualMachineId' => $vm->id])->handle(
            app(ProxmoxService::class),
            app(\App\Services\IpPoolService::class),
        );

        $vm->refresh();
        $this->assertSame(VirtualMachine::STATUS_DELETED, $vm->status);
        $this->assertNull($vm->vmid);
        $this->assertNotNull($vm->deleted_at);
        $this->assertSame(101, data_get($vm->remote_state, 'deleted_vmid'));
        $this->assertDatabaseHas('ip_addresses', [
            'id' => $address->id,
            'virtual_machine_id' => null,
            'status' => IpAddress::STATUS_RELEASED,
        ]);
    }

    public function test_delete_job_completes_when_remote_vm_is_already_missing(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, ['status' => VirtualMachine::STATUS_DELETING]);
        $this->assignedAddress($vm);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmStatus')->once()->andReturn(null);
            $mock->shouldReceive('shutdownVm')->never();
            $mock->shouldReceive('deleteVm')->never();
        });

        app(DeleteVirtualMachineJob::class, ['virtualMachineId' => $vm->id])->handle(
            app(ProxmoxService::class),
            app(\App\Services\IpPoolService::class),
        );

        $this->assertSame(VirtualMachine::STATUS_DELETED, $vm->fresh()->status);
    }

    public function test_delete_job_records_failure_and_keeps_vm_locked(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, ['status' => VirtualMachine::STATUS_DELETING]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmStatus')->once()->andThrow(new \RuntimeException('Proxmox unavailable'));
        });

        app(DeleteVirtualMachineJob::class, ['virtualMachineId' => $vm->id])->handle(
            app(ProxmoxService::class),
            app(\App\Services\IpPoolService::class),
        );

        $vm->refresh();
        $this->assertSame(VirtualMachine::STATUS_DELETING, $vm->status);
        $this->assertNotNull($vm->delete_failed_at);
        $this->assertSame('Proxmox unavailable', $vm->delete_error);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function vm(Customer $customer, array $overrides = []): VirtualMachine
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
            'last_billed_at' => now(),
        ], $overrides));
    }

    private function assignedAddress(VirtualMachine $vm): IpAddress
    {
        $pool = IpPool::create([
            'proxmox_server_id' => $vm->proxmox_server_id,
            'name' => 'THR public',
            'node' => $vm->node,
            'network_bridge' => 'vmbr0',
            'gateway' => '192.168.10.1',
            'prefix_length' => 24,
            'start_ip' => '192.168.10.50',
            'end_ip' => '192.168.10.50',
            'is_active' => true,
        ]);

        $address = IpAddress::create([
            'ip_pool_id' => $pool->id,
            'virtual_machine_id' => $vm->id,
            'address' => '192.168.10.50',
            'status' => IpAddress::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $vm->forceFill([
            'ip_address_id' => $address->id,
            'ip_address' => $address->address,
        ])->save();

        return $address;
    }
}
