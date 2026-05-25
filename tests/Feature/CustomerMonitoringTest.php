<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CustomerMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_can_open_monitoring_page(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer);

        $this->actingAs($customer, 'customer');
        $response = $this->get($this->customerBaseUrl.'/monitoring?server='.$vm->id);

        $response->assertOk();
        $response->assertSee('مانیتورینگ');
        $response->assertViewHas('selected', fn ($selected): bool => $selected?->id === $vm->id);
    }

    public function test_customer_can_fetch_own_vm_metrics(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('qemuPerformance')
                ->once()
                ->with(Mockery::type(ProxmoxServer::class), 'pve1', 101, 'hour')
                ->andReturn([
                    'node' => 'pve1',
                    'vmid' => 101,
                    'timeframe' => 'hour',
                    'samples' => [
                        ['time' => 1779746400, 'cpu' => 0.12, 'mem' => 1024, 'maxmem' => 4096, 'netin' => 128, 'netout' => 256],
                    ],
                    'latest' => ['status' => 'running', 'cpu_percent' => 12, 'memory_percent' => 25],
                    'status' => ['status' => 'running'],
                    'errors' => [],
                    'fetched_at' => now()->toISOString(),
                ]);
        });

        $this->actingAs($customer, 'customer');
        $this->getJson($this->customerBaseUrl.'/monitoring/servers/'.$vm->id.'/metrics?timeframe=hour')
            ->assertOk()
            ->assertJsonPath('data.vmid', 101)
            ->assertJsonPath('data.latest.cpu_percent', 12)
            ->assertJsonPath('data.server.id', $vm->id);
    }

    public function test_customer_cannot_fetch_another_customer_vm_metrics(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $vm = $this->readyVm($owner);

        $this->actingAs($other, 'customer');
        $this->getJson($this->customerBaseUrl.'/monitoring/servers/'.$vm->id.'/metrics')->assertNotFound();
    }

    public function test_monitoring_metrics_validates_timeframe(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer);

        $this->actingAs($customer, 'customer');
        $this->getJson($this->customerBaseUrl.'/monitoring/servers/'.$vm->id.'/metrics?timeframe=minute')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('timeframe');
    }

    private function readyVm(Customer $customer): VirtualMachine
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

        return VirtualMachine::create([
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
        ]);
    }
}
