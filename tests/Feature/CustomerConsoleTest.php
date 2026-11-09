<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerConsoleTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'console.proxy_path' => '/console-ws',
        ]);

        Customer::all();
        Customer::created(fn (Customer $c) => \DB::table('wallets')->where('customer_id', $c->id)->update(['balance' => 10_000_000]));
    }

    public function test_customer_can_open_own_vm_console_page(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer);

        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/servers/'.$vm->uuid.'/console')
            ->assertOk()
            ->assertSee('کنسول ماشین مجازی')
            ->assertSee($vm->name);
    }

    public function test_customer_cannot_open_another_customer_vm_console_page(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $vm = $this->readyVm($owner);

        $this->actingAs($other, 'customer');

        $this->get($this->customerBaseUrl.'/servers/'.$vm->uuid.'/console')->assertNotFound();
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

        $this->actingAs($customer, 'customer');

        $response = $this->postJson($this->customerBaseUrl.'/servers/'.$vm->uuid.'/console/session')
            ->assertOk()
            ->assertJsonPath('password', 'PVEVNC:secret-ticket')
            ->assertJsonMissing(['vncticket' => 'PVEVNC:secret-ticket']);

        $this->assertStringStartsWith('/console-ws/'.$vm->proxmox_server_id.'/nodes/pve1/qemu/101/vncwebsocket?', $response->json('websocket_url'));
        $this->assertStringContainsString('port=5901', $response->json('websocket_url'));
    }

    public function test_console_session_rejects_incomplete_vm(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer, ['node' => null]);

        $this->actingAs($customer, 'customer');

        $this->postJson($this->customerBaseUrl.'/servers/'.$vm->uuid.'/console/session')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'نشست کنسول ایجاد نشد. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.')
            ->assertJsonPath('code', 'not_configured')->assertJsonPath('retryable', false);
    }

    public function test_session_requests_are_recorded_without_console_secrets_and_prefill_support(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('qemuConsoleSession')->once()->andReturn(['port' => 5901, 'ticket' => 'private-console-ticket']);
        });
        $this->actingAs($customer, 'customer');
        $response = $this->postJson($this->customerBaseUrl.'/servers/'.$vm->uuid.'/console/session')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $activity = $vm->activities()->where('event', 'console.session')->sole();
        $this->assertSame($customer->id, $activity->actor_customer_id);
        $this->assertSame('customer', $activity->metadata['portal']);
        $this->assertSame($response->json('reference'), $activity->metadata['reference']);
        $this->assertStringNotContainsString('private-console-ticket', $activity->toJson());

        $this->get($this->customerBaseUrl.'/tickets/create?'.http_build_query(['virtual_machine_id' => $vm->id, 'console_reference' => $response->json('reference')]))
            ->assertOk()->assertSee($response->json('reference'))
            ->assertViewHas('selectedVirtualMachineId', $vm->id);
    }

    public function test_console_failure_has_safe_diagnostics_and_never_exposes_upstream_secrets(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('qemuConsoleSession')->once()->andThrow(new \RuntimeException('secret-api-token'));
        });
        $this->actingAs($customer, 'customer');
        $this->postJson($this->customerBaseUrl.'/servers/'.$vm->uuid.'/console/session')
            ->assertUnprocessable()->assertJsonPath('code', 'upstream')->assertJsonPath('retryable', true)
            ->assertJsonStructure(['reference'])->assertDontSee('secret-api-token');
        $activity = $vm->activities()->where('event', 'console.session')->sole();
        $this->assertSame('failed', $activity->outcome);
        $this->assertStringNotContainsString('secret-api-token', $activity->toJson());
    }

    public function test_support_prefill_cannot_reference_another_customers_machine(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $vm = $this->readyVm($owner);
        $reference = (string) Str::uuid();
        $vm->activities()->create(['event' => 'console.session', 'outcome' => 'success', 'title' => 'test', 'metadata' => ['reference' => $reference]]);
        $this->actingAs($other, 'customer')->get($this->customerBaseUrl.'/tickets/create?'.http_build_query(['virtual_machine_id' => $vm->id, 'console_reference' => $reference]))
            ->assertOk()->assertDontSee($reference)->assertDontSee($vm->name)
            ->assertViewHas('selectedVirtualMachineId', null)->assertViewHas('consoleBody', '');
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
