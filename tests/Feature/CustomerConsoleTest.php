<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use App\Services\WebsockifyConsoleTokenService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CustomerConsoleTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'console.websockify.public_path' => '/console-ws',
        ]);
    }

    public function test_customer_can_open_own_vm_console_page(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer);

        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/servers/'.$vm->id.'/console')
            ->assertOk()
            ->assertSee('VM Console')
            ->assertSee($vm->name);
    }

    public function test_customer_cannot_open_another_customer_vm_console_page(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $vm = $this->readyVm($owner);

        $this->actingAs($other, 'customer');

        $this->get($this->customerBaseUrl.'/servers/'.$vm->id.'/console')->assertNotFound();
    }

    public function test_customer_can_create_one_time_console_proxy_session(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('qemuConsoleSession')
                ->once()
                ->andReturn([
                    'port' => 5901,
                    'ticket' => 'PVEVNC:secret-ticket',
                    'headers' => ['Authorization' => 'PVEAPIToken=root@pam!panel=secret'],
                    'raw' => ['port' => 5901, 'ticket' => 'PVEVNC:secret-ticket'],
                ]);
        });
        $this->mock(WebsockifyConsoleTokenService::class, function ($mock): void {
            $mock->shouldReceive('publish')
                ->once()
                ->with(Mockery::type(ProxmoxServer::class), Mockery::type('string'), 5901, Mockery::type(CarbonInterface::class));
        });

        $this->actingAs($customer, 'customer');

        $response = $this->postJson($this->customerBaseUrl.'/servers/'.$vm->id.'/console/session')
            ->assertOk()
            ->assertJsonPath('password', 'PVEVNC:secret-ticket')
            ->assertJsonMissing(['vncticket' => 'PVEVNC:secret-ticket']);

        $this->assertStringStartsWith('/console-ws/'.$vm->proxmox_server_id.'?token=', $response->json('websocket_url'));
        $this->assertNotEmpty($response->json('session_id'));
    }

    public function test_console_session_rejects_incomplete_vm(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer, ['node' => null]);

        $this->actingAs($customer, 'customer');

        $this->postJson($this->customerBaseUrl.'/servers/'.$vm->id.'/console/session')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Console session could not be started.');
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
