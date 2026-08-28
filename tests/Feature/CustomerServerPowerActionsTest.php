<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FundsCustomerWallet;
use Tests\TestCase;

class CustomerServerPowerActionsTest extends TestCase
{
    use FundsCustomerWallet, RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_can_start_stopped_server_after_confirmation(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, ['status' => VirtualMachine::STATUS_STOPPED]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'stopped']);
            $mock->shouldReceive('startVm')->once()->andReturn(['task_id' => 'UPID:start']);
            $mock->shouldReceive('waitForTask')->once()->andReturn(['status' => 'OK']);
        });

        $this->actingAs($customer, 'customer')
            ->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/start')
            ->assertRedirect()
            ->assertSessionHas('status');

        $vm->refresh();

        $this->assertSame(VirtualMachine::STATUS_RUNNING, $vm->status);
        $this->assertSame('customer_start', data_get($vm->desired_state, 'power_intent_source'));
    }

    public function test_suspended_customer_cannot_start_server(): void
    {
        $customer = Customer::factory()->create();
        $customer->suspend('test');
        $vm = $this->vm($customer, ['status' => VirtualMachine::STATUS_STOPPED]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldNotReceive('startVm');
        });

        $this->actingAs($customer, 'customer')
            ->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/start')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(VirtualMachine::STATUS_STOPPED, $vm->fresh()->status);
    }

    public function test_customer_can_stop_running_server_after_confirmation(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, [
            'desired_state' => ['status' => VirtualMachine::STATUS_RUNNING, 'power_generation' => 2],
        ]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('shutdownVm')->once()->andReturn(['task_id' => 'UPID:shutdown']);
            $mock->shouldReceive('waitForTask')->once()->andReturn(['status' => 'OK']);
            $mock->shouldReceive('waitForVmStopped')->once()->andReturn(['status' => 'stopped']);
        });

        $this->actingAs($customer, 'customer')
            ->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/stop', ['power_generation' => 2])
            ->assertRedirect()
            ->assertSessionHas('status');

        $vm->refresh();

        $this->assertSame(VirtualMachine::STATUS_STOPPED, $vm->status);
        $this->assertSame(3, data_get($vm->desired_state, 'power_generation'));
        $this->assertSame('customer_stop', data_get($vm->desired_state, 'power_intent_source'));
    }

    /** @param array<string, mixed> $overrides */
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
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => now(),
        ], $overrides));
    }
}
