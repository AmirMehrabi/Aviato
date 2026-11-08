<?php

namespace Tests\Feature;

use App\Jobs\ApplyVmUpgradeJob;
use App\Jobs\ObserveVmHardware;
use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\UsageAccrual;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Models\VmDisk;
use App\Models\VmUpgradeOrder;
use App\Services\BillingService;
use App\Services\InvoiceService;
use App\Services\PricingBoundaryService;
use App\Services\ProxmoxService;
use App\Services\UsageBillingService;
use App\Services\VmHardwareService;
use App\Services\VmTransferService;
use App\Services\VmUpgradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminVmConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function vm(array $attributes = []): VirtualMachine
    {
        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000000]);
        $bundle = VmBundle::create(['name' => 'Starter', 'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40,
            'ip_count' => 1, 'monthly_price' => 73000, 'stopped_monthly_price' => 7300, 'is_active' => true]);
        $server = ProxmoxServer::create(['name' => 'Cluster', 'host' => 'pve.local', 'port' => 8006,
            'realm' => 'pam', 'username' => 'root', 'api_token_id' => 'panel', 'api_token_secret' => 'secret', 'is_active' => true]);

        return VirtualMachine::create(array_merge(['customer_id' => $customer->id, 'proxmox_server_id' => $server->id,
            'vm_bundle_id' => $bundle->id, 'name' => 'test-vm', 'node' => 'pve1', 'vmid' => 101,
            'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40, 'ip_count' => 1, 'storage' => 'local-lvm',
            'status' => 'running', 'provisioning_status' => 'ready', 'last_billed_at' => now()->subHours(2)], $attributes));
    }

    private function remote(array $changes = [], string $status = 'running', array $pending = []): void
    {
        $this->mock(ProxmoxService::class, function ($mock) use ($changes, $status, $pending): void {
            $mock->shouldReceive('vmEffectiveConfig')->andReturn(array_merge(['digest' => 'v1', 'name' => 'test-vm', 'cores' => 2,
                'memory' => 4096, 'scsi0' => 'local-lvm:vm-101-disk-0,size=40G'], $changes));
            $mock->shouldReceive('vmPendingConfig')->andReturn($pending);
            $mock->shouldReceive('vmStatus')->andReturn(['status' => $status]);
        });
    }

    private function pricingInput(array $changes = []): array
    {
        return array_merge(['kind' => 'pricing', 'pricing_mode' => 'custom', 'custom_monthly_price' => 146000,
            'custom_stopped_monthly_price' => 14600, 'reason' => 'Customer agreement'], $changes);
    }

    public function test_admin_reviews_pricing_then_confirms_without_repricing_elapsed_usage(): void
    {
        $this->travelTo(now()->startOfSecond());
        $vm = $this->vm();
        $admin = User::factory()->create();
        $base = 'https://admin.localhost/virtual-machines/'.$vm->uuid;
        $response = $this->actingAs($admin, 'admin')->post($base.'/configuration/preview', $this->pricingInput());
        $response->assertOk()->assertSee('مرور و تأیید')->assertSee('تأیید و ثبت تغییرات');
        $this->assertSame('inherit', $vm->fresh()->pricing_mode);
        $quote = $response->viewData('quote');
        $this->post($base.'/configuration/confirm', ['quote' => $quote['token'], 'confirm' => 1])->assertRedirect();
        $this->assertSame('custom', $vm->fresh()->pricing_mode);
        $this->assertSame(200, (int) UsageAccrual::where('virtual_machine_id', $vm->id)->sum('amount'));
        $this->travel(1)->hours();
        app(UsageBillingService::class)->accrueVm($vm->fresh());
        $this->assertSame(400, (int) UsageAccrual::where('virtual_machine_id', $vm->id)->sum('amount'));
        $this->assertSame(2, count(UsageAccrual::where('virtual_machine_id', $vm->id)->first()->segments));
        $this->post($base.'/configuration/confirm', ['quote' => $quote['token'], 'confirm' => 1])->assertRedirect();
        $this->assertSame(1, $vm->configurationChanges()->count());
    }

    public function test_vm_detail_exposes_clear_hardware_and_pricing_journey(): void
    {
        $vm = $this->vm();
        $this->actingAs(User::factory()->create(), 'admin')->get('https://admin.localhost/virtual-machines/'.$vm->uuid)
            ->assertOk()->assertSee('سخت‌افزار و قیمت‌گذاری')->assertSee('مرور قیمت جدید')->assertSee('بررسی مجدد');
    }

    public function test_zero_custom_prices_are_valid_and_override_bundle_running_and_stopped_prices(): void
    {
        $vm = $this->vm();
        $actor = User::factory()->create();
        $service = app(VmHardwareService::class);
        $quote = $service->quote($vm, $actor, 'pricing', $this->pricingInput(['custom_monthly_price' => 0, 'custom_stopped_monthly_price' => 0]));
        $service->confirm($vm, $actor, $quote['token']);
        $this->assertSame(0.0, app(BillingService::class)->hourlyWhenRunning($vm->fresh()));
        $this->assertSame(0.0, app(BillingService::class)->persistentHourly($vm->fresh()));
    }

    public function test_expired_and_tampered_quotes_cannot_change_prices(): void
    {
        $vm = $this->vm();
        $actor = User::factory()->create();
        $quote = app(VmHardwareService::class)->quote($vm, $actor, 'pricing', $this->pricingInput());
        $base = 'https://admin.localhost/virtual-machines/'.$vm->uuid.'/configuration/confirm';
        $this->actingAs($actor, 'admin')->post($base, ['quote' => $quote['token'].'x', 'confirm' => 1])->assertSessionHasErrors('configuration');
        $this->travel(11)->minutes();
        $this->post($base, ['quote' => $quote['token'], 'confirm' => 1])->assertSessionHasErrors('configuration');
        $this->assertSame('inherit', $vm->fresh()->pricing_mode);
    }

    public function test_changed_bundle_price_invalidates_a_pricing_quote(): void
    {
        $vm = $this->vm();
        $actor = User::factory()->create();
        $quote = app(VmHardwareService::class)->quote($vm, $actor, 'pricing', $this->pricingInput());
        $vm->bundle->update(['monthly_price' => 219000]);
        $this->expectException(ValidationException::class);
        app(VmHardwareService::class)->confirm($vm->fresh(), $actor, $quote['token']);
    }

    public function test_read_only_admin_cannot_change_hardware_or_pricing(): void
    {
        $vm = $this->vm();
        $actor = User::factory()->create(['role' => 'support']);
        $this->actingAs($actor, 'admin')->post('https://admin.localhost/virtual-machines/'.$vm->uuid.'/configuration/preview', $this->pricingInput())->assertForbidden();
    }

    public function test_sync_accepts_external_hardware_and_preserves_custom_price_and_bundle(): void
    {
        $this->travelTo(now()->startOfSecond());
        $vm = $this->vm(['pricing_mode' => 'custom', 'custom_monthly_price' => 73000, 'custom_stopped_monthly_price' => 7300]);
        $this->remote(['cores' => 4, 'memory' => 8192, 'scsi0' => 'local-lvm:vm-101-disk-0,size=80G']);
        $actor = User::factory()->create();
        $service = app(VmHardwareService::class);
        $quote = $service->quote($vm, $actor, 'sync', ['reason' => 'External upgrade']);
        $this->assertSame(2, $vm->fresh()->cpu_cores);
        $service->confirm($vm, $actor, $quote['token']);
        $this->assertSame(4, $vm->fresh()->cpu_cores);
        $this->assertSame(8, $vm->fresh()->ram_gb);
        $this->assertSame(80, $vm->fresh()->disk_gb);
        $this->assertSame($vm->vm_bundle_id, $vm->fresh()->vm_bundle_id);
        $this->assertSame(73000, $vm->fresh()->custom_monthly_price);
        $this->assertSame(200, (int) UsageAccrual::where('virtual_machine_id', $vm->id)->sum('amount'));
    }

    public function test_pending_reboot_configuration_cannot_be_accepted_as_active_hardware(): void
    {
        $vm = $this->vm();
        $this->remote(['cores' => 4], pending: [['key' => 'memory', 'value' => 4096, 'pending' => 8192]]);
        $this->expectException(ValidationException::class);
        app(VmHardwareService::class)->quote($vm, User::factory()->create(), 'sync', ['reason' => 'External upgrade']);
    }

    public function test_disk_shrink_and_unaccepted_external_drift_block_managed_changes(): void
    {
        $vm = $this->vm();
        $this->remote();
        $this->expectException(ValidationException::class);
        app(VmHardwareService::class)->quote($vm, User::factory()->create(), 'hardware', ['cpu_cores' => 1, 'ram_gb' => 2, 'disk_gb' => 20, 'reason' => 'Resize request']);
    }

    public function test_hardware_request_is_queued_once_and_preserves_original_stopped_state(): void
    {
        Bus::fake();
        $vm = $this->vm(['status' => 'stopped']);
        $this->remote(status: 'stopped');
        $actor = User::factory()->create();
        $service = app(VmHardwareService::class);
        $quote = $service->quote($vm, $actor, 'hardware', ['cpu_cores' => 1, 'ram_gb' => 2, 'disk_gb' => 40, 'reason' => 'Resize request']);
        $order = $service->confirm($vm, $actor, $quote['token']);
        $this->assertSame('stopped', $order->after_snapshot['original_status']);
        $service->confirm($vm->fresh(), $actor, $quote['token']);
        $this->assertSame(1, $vm->upgradeOrders()->count());
        $this->assertSame('admin', $vm->operations()->first()->actor_type);
        Bus::assertDispatchedTimes(ApplyVmUpgradeJob::class, 1);
    }

    public function test_hardware_job_applies_cpu_ram_reductions_and_keeps_stopped_vm_stopped(): void
    {
        Bus::fake();
        $this->travelTo(now()->startOfSecond());
        $vm = $this->vm(['status' => 'stopped']);
        $this->remote(status: 'stopped');
        $actor = User::factory()->create();
        $service = app(VmHardwareService::class);
        $quote = $service->quote($vm, $actor, 'hardware', ['cpu_cores' => 1, 'ram_gb' => 2, 'disk_gb' => 40, 'reason' => 'Resize request']);
        $order = $service->confirm($vm, $actor, $quote['token']);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmEffectiveConfig')->once()->andReturn(['digest' => 'v1', 'cores' => 2, 'memory' => 4096, 'scsi0' => 'local-lvm:vm-101-disk-0,size=40G']);
            $mock->shouldReceive('vmEffectiveConfig')->once()->andReturn(['digest' => 'v2', 'cores' => 1, 'memory' => 2048, 'scsi0' => 'local-lvm:vm-101-disk-0,size=40G']);
            $mock->shouldReceive('vmStatus')->twice()->andReturn(['status' => 'stopped']);
            $mock->shouldReceive('updateVmHardware')->once()->withAnyArgs()->andReturn(['task_id' => 'UPID:hardware']);
            $mock->shouldReceive('waitForTask')->once()->andReturn(['status' => 'stopped', 'exitstatus' => 'OK']);
            $mock->shouldNotReceive('startVm');
            $mock->shouldNotReceive('shutdownVm');
        });
        (new ApplyVmUpgradeJob($order->id))->handle(app(ProxmoxService::class));
        $this->assertSame('succeeded', $order->fresh()->status);
        $this->assertSame(1, $vm->fresh()->cpu_cores);
        $this->assertSame(2, $vm->fresh()->ram_gb);
        $this->assertSame('stopped', $vm->fresh()->status);
        $this->assertSame(20, (int) UsageAccrual::where('virtual_machine_id', $vm->id)->sum('amount'));
        $this->assertSame('hardware', $vm->configurationChanges()->first()->kind);
    }

    public function test_custom_priced_machine_cannot_silently_return_to_bundle_pricing_on_customer_upgrade(): void
    {
        $vm = $this->vm(['pricing_mode' => 'custom', 'custom_monthly_price' => 73000, 'custom_stopped_monthly_price' => 7300]);
        $this->expectException(ValidationException::class);
        app(VmUpgradeService::class)->requestBundleUpgrade($vm->customer, $vm, $vm->bundle);
    }

    public function test_unreachable_proxmox_preserves_last_good_observation(): void
    {
        $vm = $this->vm(['hardware_observation' => ['cpu_cores' => 2]]);
        $this->mock(ProxmoxService::class, fn ($mock) => $mock->shouldReceive('vmEffectiveConfig')->andThrow(new \RuntimeException('Offline')));
        $this->actingAs(User::factory()->create(), 'admin')->post('https://admin.localhost/virtual-machines/'.$vm->uuid.'/configuration/refresh')->assertSessionHas('error');
        $this->assertSame(['cpu_cores' => 2], $vm->fresh()->hardware_observation);
        $this->assertNotNull($vm->fresh()->hardware_sync_error);
    }

    public function test_normalization_accounts_for_cpu_sockets_and_terabyte_disks_and_excludes_iso(): void
    {
        $vm = $this->vm();
        $value = app(VmHardwareService::class)->normalize($vm, ['cores' => 2, 'sockets' => 2, 'memory' => 1536,
            'scsi0' => 'local-lvm:vm-101-disk-0,size=1.5T', 'ide2' => 'local:iso/test.iso,media=cdrom']);
        $this->assertSame(4, $value['cpu_cores']);
        $this->assertSame(1536, $value['ram_mb']);
        $this->assertSame((int) (1.5 * 1024 ** 4), $value['disk_bytes']);
        $this->assertCount(1, $value['disks']);
    }

    public function test_invoice_separates_price_revisions_within_the_same_day(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(8));
        $vm = $this->vm();
        $actor = User::factory()->create();
        $service = app(VmHardwareService::class);
        $quote = $service->quote($vm, $actor, 'pricing', $this->pricingInput());
        $service->confirm($vm, $actor, $quote['token']);
        $this->travel(2)->hours();
        app(UsageBillingService::class)->accrueVm($vm->fresh());
        app(UsageBillingService::class)->settleDate(now()->toDateString());
        $invoice = app(InvoiceService::class)->generateForCustomer($vm->customer, now()->startOfDay(), now()->endOfDay());
        $this->assertCount(2, $invoice->items);
        $this->assertEquals(600, $invoice->items->sum('subtotal'));
    }

    public function test_fractional_charges_are_preserved_across_repeated_price_changes(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(8));
        $vm = $this->vm(['pricing_mode' => 'custom', 'custom_monthly_price' => 730, 'custom_stopped_monthly_price' => 0, 'last_billed_at' => now()]);
        $actor = User::factory()->create();
        $service = app(VmHardwareService::class);
        foreach ([731, 732] as $price) {
            $this->travel(20)->minutes();
            $quote = $service->quote($vm->fresh(), $actor, 'pricing', $this->pricingInput(['custom_monthly_price' => $price, 'custom_stopped_monthly_price' => 0]));
            $service->confirm($vm->fresh(), $actor, $quote['token']);
        }
        $this->travel(20)->minutes();
        app(UsageBillingService::class)->accrueVm($vm->fresh());
        $accrual = UsageAccrual::where('virtual_machine_id', $vm->id)->firstOrFail();
        $this->assertSame(1, $accrual->amount);
        $this->assertCount(3, $accrual->segments);
    }

    public function test_global_bundle_change_checkpoints_old_usage_and_preserves_custom_price(): void
    {
        $this->travelTo(now()->startOfSecond());
        $vm = $this->vm();
        $custom = $vm->replicate(['uuid']);
        $custom->fill(['vmid' => 102, 'pricing_mode' => 'custom', 'custom_monthly_price' => 7300, 'custom_stopped_monthly_price' => 0]);
        $custom->save();
        app(PricingBoundaryService::class)->bundle($vm->bundle, ['monthly_price' => 146000]);
        $this->assertSame(200, (int) UsageAccrual::where('virtual_machine_id', $vm->id)->sum('amount'));
        $this->assertSame(20, (int) UsageAccrual::where('virtual_machine_id', $custom->id)->sum('amount'));
        $this->assertEquals(200, app(BillingService::class)->hourlyWhenRunning($vm->fresh()));
        $this->assertEquals(10, app(BillingService::class)->hourlyWhenRunning($custom->fresh()));
    }

    public function test_drift_filter_only_lists_machines_with_observed_differences(): void
    {
        $vm = $this->vm(['hardware_drift' => true]);
        $other = $vm->replicate(['uuid']);
        $other->fill(['vmid' => 102, 'name' => 'unchanged-vm', 'hardware_drift' => false]);
        $other->save();
        $this->actingAs(User::factory()->create(), 'admin')
            ->get('https://admin.localhost/virtual-machines?hardware_drift=1')
            ->assertOk()->assertViewHas('vms', fn ($machines) => $machines->modelKeys() === [$vm->id]);
    }

    public function test_background_observation_never_accepts_external_hardware_or_prices(): void
    {
        $vm = $this->vm();
        $this->remote(['cores' => 4]);
        (new ObserveVmHardware($vm->id))->handle(app(VmHardwareService::class));
        $this->assertTrue($vm->fresh()->hardware_drift);
        $this->assertSame(2, $vm->fresh()->cpu_cores);
        $this->assertSame('inherit', $vm->fresh()->pricing_mode);
        $this->assertSame(0, $vm->configurationChanges()->count());
    }

    public function test_vm_with_an_active_operation_cannot_change_its_price(): void
    {
        $vm = $this->vm();
        $vm->operations()->create(['action' => 'shutdown', 'active_vm_id' => $vm->id, 'actor_type' => 'admin']);
        $this->expectException(ValidationException::class);
        app(VmHardwareService::class)->quote($vm, User::factory()->create(), 'pricing', $this->pricingInput());
    }

    public function test_another_admin_cannot_confirm_someone_elses_quote(): void
    {
        $vm = $this->vm();
        $quote = app(VmHardwareService::class)->quote($vm, User::factory()->create(), 'pricing', $this->pricingInput());
        $this->expectException(ValidationException::class);
        app(VmHardwareService::class)->confirm($vm, User::factory()->create(), $quote['token']);
    }

    public function test_observation_scheduler_excludes_lxc_and_queues_supported_machines(): void
    {
        Bus::fake();
        $vm = $this->vm();
        $lxc = $vm->replicate(['uuid']);
        $lxc->fill(['vmid' => 102, 'provider_metadata' => ['guest_type' => 'lxc']]);
        $lxc->save();
        $this->artisan('vm-hardware:observe')->assertSuccessful();
        Bus::assertDispatchedTimes(ObserveVmHardware::class, 1);
        Bus::assertDispatched(ObserveVmHardware::class, fn ($job) => $job->vmId === $vm->id);
    }

    public function test_custom_price_stays_attached_when_other_vm_metadata_is_edited(): void
    {
        $vm = $this->vm(['cpu_cores' => 4, 'ram_gb' => 8, 'pricing_mode' => 'custom', 'custom_monthly_price' => 7300, 'custom_stopped_monthly_price' => 0]);
        $this->actingAs(User::factory()->create(), 'admin')->put('https://admin.localhost/virtual-machines/'.$vm->uuid, [
            'customer_id' => $vm->customer_id, 'project_id' => $vm->project_id,
            'proxmox_server_id' => $vm->proxmox_server_id, 'vm_bundle_id' => $vm->vm_bundle_id,
            'name' => $vm->name, 'display_name' => 'New label', 'node' => $vm->node, 'vmid' => $vm->vmid,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(4, $vm->fresh()->cpu_cores);
        $this->assertSame(8, $vm->fresh()->ram_gb);
        $this->assertSame(7300, $vm->fresh()->custom_monthly_price);
    }

    public function test_transfer_preserves_custom_agreement_and_keeps_elapsed_charges_with_old_owner(): void
    {
        $this->travelTo(now()->startOfSecond());
        $vm = $this->vm(['pricing_mode' => 'custom', 'custom_monthly_price' => 73000, 'custom_stopped_monthly_price' => 7300, 'unbilled_amount' => 50]);
        $oldOwner = $vm->customer_id;
        $newOwner = Customer::factory()->create();
        app(VmTransferService::class)->transferVm($vm, $newOwner, User::factory()->create()->id);
        $this->assertSame('custom', $vm->fresh()->pricing_mode);
        $this->assertSame(73000, $vm->fresh()->custom_monthly_price);
        $this->assertSame(250, (int) UsageAccrual::where('customer_id', $oldOwner)->sum('amount'));
        $this->assertSame(0, (int) UsageAccrual::where('customer_id', $newOwner->id)->sum('amount'));
        $this->travel(1)->hours();
        app(UsageBillingService::class)->accrueVm($vm->fresh());
        $this->assertSame(100, (int) UsageAccrual::where('customer_id', $newOwner->id)->sum('amount'));
    }

    public function test_insufficient_node_capacity_blocks_hardware_increase_before_queuing(): void
    {
        Bus::fake();
        $vm = $this->vm();
        $this->remote();
        app(ProxmoxService::class)->shouldReceive('hardwareCapacity')->once()->andReturn(['memory_free' => 0, 'storage_available' => 0]);
        $this->expectException(ValidationException::class);
        app(VmHardwareService::class)->quote($vm, User::factory()->create(), 'hardware', ['cpu_cores' => 4, 'ram_gb' => 8, 'disk_gb' => 80, 'reason' => 'Resize request']);
    }

    public function test_hardware_job_refuses_changes_when_proxmox_changed_after_confirmation(): void
    {
        Bus::fake();
        $vm = $this->vm();
        $this->remote();
        $actor = User::factory()->create();
        $service = app(VmHardwareService::class);
        $quote = $service->quote($vm, $actor, 'hardware', ['cpu_cores' => 1, 'ram_gb' => 2, 'disk_gb' => 40, 'reason' => 'Resize request']);
        $order = $service->confirm($vm, $actor, $quote['token']);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmEffectiveConfig')->once()->andReturn(['digest' => 'externally-changed', 'cores' => 8, 'memory' => 4096, 'scsi0' => 'local-lvm:vm-101-disk-0,size=40G']);
            $mock->shouldNotReceive('updateVmHardware');
            $mock->shouldNotReceive('shutdownVm');
        });
        (new ApplyVmUpgradeJob($order->id))->handle(app(ProxmoxService::class));
        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame(2, $vm->fresh()->cpu_cores);
        $this->assertFalse($vm->fresh()->isActionLocked());
    }

    public function test_retry_recognizes_its_existing_extra_disk_without_attaching_another(): void
    {
        $vm = $this->vm();
        $order = VmUpgradeOrder::create(['customer_id' => $vm->customer_id, 'virtual_machine_id' => $vm->id,
            'type' => 'extra_disk', 'status' => 'reconciliation_required', 'before_snapshot' => $vm->desiredStateSnapshot(),
            'after_snapshot' => ['size_gb' => 10, 'storage' => 'local-lvm'], 'progress' => ['selected_disk' => ['device' => 'scsi1']]]);
        VmDisk::create(['virtual_machine_id' => $vm->id, 'vm_upgrade_order_id' => $order->id,
            'disk_device' => 'pending-'.$order->id, 'storage' => 'local-lvm', 'size_gb' => 10, 'status' => 'pending']);
        $this->mock(ProxmoxService::class, function ($mock) use ($order): void {
            $mock->shouldReceive('vmConfig')->twice()->andReturn(['scsi0' => 'local-lvm:vm-101-disk-0,size=40G',
                'scsi1' => 'local-lvm:vm-101-disk-1,size=10G,serial=aviato-upgrade-'.$order->id]);
            $mock->shouldNotReceive('attachDisk');
        });
        (new ApplyVmUpgradeJob($order->id))->handle(app(ProxmoxService::class));
        $this->assertSame('succeeded', $order->fresh()->status);
        $this->assertSame('scsi1', $order->fresh()->disk->disk_device);
    }
}
