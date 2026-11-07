<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Services\VmActivityRecorder;
use App\Services\VmActivityTimeline;
use App\Services\VmOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
                ->assertSee('min-h-screen bg-[#F8FAFC]', false)
                ->assertSee('aria-label="بخش‌های سرور"', false)
                ->assertSee('aria-label="باز کردن کنسول سرور', false)
                ->assertSee(route('customer.servers.restart', $vm, false))
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

    public function test_only_active_operation_banner_is_shown_outside_activity_tab(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $operation = app(VmOperationService::class)->request($vm, 'shutdown', 'customer', $customer->id);
        $this->actingAs($customer, 'customer');
        $url = $this->base.'/servers/'.$vm->uuid;
        foreach (['', '/resources', '/network'] as $suffix) {
            $this->get($url.$suffix)->assertOk()
                ->assertSee('مشاهده در فعالیت‌ها')
                ->assertDontSee('درخواست‌های سرور');
        }
        $this->get($url.'/activity')->assertOk()->assertDontSee('مشاهده در فعالیت‌ها')
            ->assertSee('data-operation-id="'.$operation->id.'"', false)
            ->assertSee('در صف انجام');
        app(VmOperationService::class)->finish($operation, false);
        $this->get($url)->assertOk()->assertDontSee('مشاهده در فعالیت‌ها')
            ->assertSee('بررسی خاموش کردن اجباری');
    }

    public function test_activity_timeline_keeps_one_operation_row_through_completion(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        app(VmActivityRecorder::class)->record($vm, 'rename', 'success', 'نام نمایشی تغییر کرد');
        $operation = app(VmOperationService::class)->request($vm, 'shutdown', 'customer', $customer->id);
        $timeline = app(VmActivityTimeline::class);
        $this->assertSame(2, $timeline->forVm($vm)->total());
        app(VmOperationService::class)->finish($operation, false);
        $page = $timeline->forVm($vm);
        $this->assertSame(2, $page->total());
        $this->assertSame('failed', $page->getCollection()->firstWhere('operation.id', $operation->id)->outcome);
        $this->actingAs($customer, 'customer')->get($this->base.'/servers/'.$vm->uuid.'/activity')
            ->assertOk()->assertSee('نام نمایشی تغییر کرد')
            ->assertSee('خاموش کردن · انجام نشد');
    }

    public function test_activity_timeline_deduplicates_upgrade_receipts_and_preserves_legacy_events(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $vm->operations()->create(['action' => 'upgrade', 'status' => 'succeeded', 'actor_type' => 'customer', 'details' => ['upgrade_order_id' => 42]]);
        $recorder = app(VmActivityRecorder::class);
        $recorder->record($vm, 'upgrade', 'requested', 'درخواست ارتقا', metadata: ['order_id' => 42]);
        $recorder->record($vm, 'upgrade', 'succeeded', 'انجام ارتقا', metadata: ['order_id' => 42]);
        $recorder->record($vm, 'upgrade', 'succeeded', 'ارتقای قدیمی', metadata: ['order_id' => 41]);
        $recorder->record($vm, 'power', 'succeeded', 'عملیات قدیمی', metadata: ['operation_id' => 9999]);
        $foreign = $vm->replicate(['uuid']);
        $foreign->forceFill(['customer_id' => Customer::factory()->create()->id, 'vmid' => 322])->save();
        $recorder->record($foreign, 'rename', 'success', 'رویداد مشتری دیگر');
        $page = app(VmActivityTimeline::class)->forVm($vm);
        $this->assertSame(3, $page->total());
        $this->assertContains('ارتقای قدیمی', $page->pluck('title'));
        $this->assertContains('عملیات قدیمی', $page->pluck('title'));
        $this->assertNotContains('رویداد مشتری دیگر', $page->pluck('title'));
    }

    public function test_activity_timeline_paginates_operations_and_other_events_together(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        for ($index = 0; $index < 12; $index++) {
            $vm->operations()->create(['action' => 'start', 'status' => 'succeeded', 'actor_type' => 'customer']);
            app(VmActivityRecorder::class)->record($vm, 'rename', 'success', 'تغییر نام '.$index);
        }
        $page = app(VmActivityTimeline::class)->forVm($vm);
        $this->assertSame(24, $page->total());
        $this->assertCount(20, $page->items());
        $this->assertTrue($page->hasMorePages());
    }

    public function test_running_vm_has_separate_normal_and_confirmed_force_stop_controls(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $this->actingAs($customer, 'customer')->get($this->base.'/servers/'.$vm->uuid)
            ->assertOk()->assertSee(route('customer.servers.stop', $vm, false))
            ->assertSee(route('customer.servers.force-stop', $vm, false))
            ->assertSee('سیستم‌عامل به‌صورت عادی خاموش می‌شود.')
            ->assertSee('عملیات بیشتر')->assertSee('<dialog', false)
            ->assertSee('اطلاعات ذخیره‌نشده ممکن است از بین برود.')
            ->assertDontSee('سرور پاسخ نمی‌دهد؟')->assertDontSee('درخواست‌های سرور');
        $vm->update(['status' => 'stopped']);
        $this->get($this->base.'/servers/'.$vm->uuid)->assertOk()
            ->assertSee(route('customer.servers.start', $vm, false))
            ->assertDontSee('عملیات بیشتر')->assertDontSee('<dialog', false);
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
