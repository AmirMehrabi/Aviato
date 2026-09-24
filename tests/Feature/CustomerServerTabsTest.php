<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Services\VmActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerServerTabsTest extends TestCase
{
    use RefreshDatabase;

    private string $base = 'https://cp.localhost';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_each_server_tab_is_a_dedicated_accessible_page(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $this->actingAs($customer, 'customer');

        foreach (['', '/resources', '/billing', '/network', '/upgrade', '/rebuild', '/delete', '/activity'] as $suffix) {
            $this->get($this->base.'/servers/'.$vm->uuid.$suffix)
                ->assertOk()
                ->assertSee($vm->display_name)
                ->assertSee('aria-label="بخش‌های سرور"', false)
                ->assertSee('aria-label="باز کردن کنسول سرور', false)
                ->assertSee('نمای کلی')
                ->assertSee('فعالیت');
        }
    }

    public function test_server_tabs_and_activity_are_scoped_to_the_current_customer_workspace(): void
    {
        $owner = Customer::factory()->create();
        $vm = $this->vm($owner);
        app(VmActivityRecorder::class)->record($vm, 'power', 'succeeded', 'سرور روشن شد', actor: $owner);

        $this->actingAs($owner, 'customer')
            ->get($this->base.'/servers/'.$vm->uuid.'/activity')
            ->assertOk()
            ->assertSee('سرور روشن شد');

        $other = Customer::factory()->create();
        $this->actingAs($other, 'customer');

        foreach (['/resources', '/billing', '/network', '/upgrade', '/rebuild', '/delete', '/activity'] as $suffix) {
            $this->get($this->base.'/servers/'.$vm->uuid.$suffix)->assertNotFound();
        }
    }

    public function test_invalid_actions_return_to_their_dedicated_tabs(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $url = $this->base.'/servers/'.$vm->uuid;
        $this->actingAs($customer, 'customer');

        $this->from($url.'/upgrade')->post($url.'/upgrades/extra-disk', ['size_gb' => 7])
            ->assertRedirect($url.'/upgrade')->assertSessionHasErrors('size_gb');
        $this->from($url.'/rebuild')->post($url.'/rebuild', ['rebuild_confirmation' => 'wrong'])
            ->assertRedirect($url.'/rebuild')->assertSessionHas('error');
        $this->from($url.'/delete')->delete($url, ['delete_confirmation' => 'wrong'])
            ->assertRedirect($url.'/delete')->assertSessionHasErrors('delete_confirmation');
    }

    private function vm(Customer $customer): VirtualMachine
    {
        $server = ProxmoxServer::create([
            'name' => 'Tab Test Proxmox',
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

        return VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $server->id,
            'vmid' => 321,
            'name' => 'tab-test-vm',
            'hostname' => 'tab-test-vm',
            'node' => 'pve1',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => now(),
        ]);
    }
}
