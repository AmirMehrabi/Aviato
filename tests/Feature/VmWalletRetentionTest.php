<?php

namespace Tests\Feature;

use App\Jobs\DeleteVirtualMachineJob;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\InfrastructureLocation;
use App\Models\ResourceRate;
use App\Models\UsageAccrual;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmBundle;
use App\Models\VmBundleLocationMapping;
use App\Models\VmDisk;
use App\Notifications\VmWalletRetentionNotification;
use App\Services\BillingService;
use App\Services\ProxmoxService;
use App\Services\UsageBalanceService;
use App\Services\UsageBillingService;
use App\Services\VirtualMachineDeletionService;
use App\Services\VmWalletRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VmWalletRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        Queue::fake();
    }

    private function machine(Customer $owner, array $attributes = []): VirtualMachine
    {
        $bundle = VmBundle::create([
            'name' => 'Retention '.uniqid(), 'cpu_cores' => 2, 'ram_gb' => 4,
            'disk_gb' => 40, 'ip_count' => 1, 'monthly_price' => 730000,
            'stopped_monthly_price' => 73000, 'is_active' => true,
        ]);

        return VirtualMachine::create(array_merge([
            'customer_id' => $owner->id, 'vm_bundle_id' => $bundle->id,
            'name' => 'سرور آزمایشی', 'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40,
            'ip_count' => 1, 'status' => VirtualMachine::STATUS_STOPPED,
            'provisioning_status' => VirtualMachine::PROVISION_READY, 'last_billed_at' => now(),
        ], $attributes));
    }

    public function test_stopped_bundle_price_and_zero_are_respected_and_usage_splits_on_transition(): void
    {
        $owner = Customer::factory()->create();
        $vm = $this->machine($owner, ['status' => VirtualMachine::STATUS_RUNNING]);
        $this->travel(1)->hours();
        app(UsageBillingService::class)->accrueVm($vm);
        $vm->refresh()->update(['status' => VirtualMachine::STATUS_STOPPED]);
        $this->travel(1)->hours();
        app(UsageBillingService::class)->accrueVm($vm);
        $this->assertSame(1100, (int) UsageAccrual::query()->where('customer_id', $owner->id)->sum('amount'));
        $vm->bundle->update(['stopped_monthly_price' => 0]);
        $this->assertSame(0.0, app(BillingService::class)->persistentHourly($vm->fresh()));
    }

    public function test_existing_bundle_without_stopped_price_uses_disk_and_ip_with_bundle_cap(): void
    {
        $owner = Customer::factory()->create();
        $vm = $this->machine($owner);
        $vm->bundle->update(['stopped_monthly_price' => null]);
        ResourceRate::create(['resource' => ResourceRate::DISK, 'label' => 'Disk', 'unit' => 'GB', 'hourly_price' => 10, 'is_active' => true]);
        ResourceRate::create(['resource' => ResourceRate::IP, 'label' => 'IP', 'unit' => 'IP', 'hourly_price' => 20, 'is_active' => true]);
        $this->assertSame(420.0, app(BillingService::class)->persistentHourly($vm->fresh()));
        $vm->bundle->update(['monthly_price' => 73000]);
        $this->assertSame(100.0, app(BillingService::class)->persistentHourly($vm->fresh()));
    }

    public function test_legacy_negative_wallet_gets_full_default_window_and_partial_payment_does_not_extend_it(): void
    {
        $owner = Customer::factory()->create();
        $owner->wallet()->update(['balance' => -100000]);
        $vm = $this->machine($owner, ['created_at' => now()->subMonth()]);
        $service = app(VmWalletRetentionService::class);
        $service->reconcile($owner, $vm);
        $deadline = $vm->fresh()->wallet_retention_expires_at;
        $this->assertTrue($deadline->equalTo(now()->addDays(7)));
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_RETENTION_DAYS, 1, 'integer', 'billing');
        $owner->wallet()->update(['balance' => -50000]);
        $this->travel(1)->days();
        $service->reconcile($owner, $vm->fresh());
        $this->assertTrue($vm->fresh()->wallet_retention_expires_at->equalTo($deadline));
        $other = $this->machine($owner);
        $service->reconcile($owner, $other);
        $this->assertTrue($other->fresh()->wallet_retention_expires_at->equalTo(now()->addDay()));
    }

    public function test_unsettled_usage_and_zero_balance_start_retention_and_admin_exemption_clears_it(): void
    {
        $owner = Customer::factory()->create();
        $owner->wallet()->update(['balance' => 100]);
        $vm = $this->machine($owner, ['last_billed_at' => now()->subHour()]);
        app(VmWalletRetentionService::class)->reconcile($owner, $vm);
        $this->assertNotNull($vm->fresh()->wallet_depleted_at);
        $owner->update(['auto_suspend_vms' => false]);
        app(VmWalletRetentionService::class)->reconcile($owner, $vm->fresh());
        $this->assertNull($vm->fresh()->wallet_retention_expires_at);
        Queue::assertNotPushed(DeleteVirtualMachineJob::class);
    }

    public function test_reminders_are_sent_once_with_farsi_deadline_and_stop_reason(): void
    {
        Notification::fake();
        $owner = Customer::factory()->create();
        $vm = $this->machine($owner);
        $service = app(VmWalletRetentionService::class);
        $service->reconcile($owner, $vm);
        $vm->refresh()->update(['wallet_restriction' => ['wallet_stopped' => true]]);
        $service->reconcile($owner, $vm->fresh());
        $this->travel(5)->days();
        $service->reconcile($owner, $vm->fresh());
        $service->reconcile($owner, $vm->fresh());
        $this->travel(1)->days();
        $service->reconcile($owner, $vm->fresh());
        Notification::assertSentToTimes($owner, VmWalletRetentionNotification::class, 4);
        Notification::assertSentTo($owner, VmWalletRetentionNotification::class, function ($notice) use ($owner): bool {
            $data = $notice->toArray($owner);

            return $data['stage'] === '24h'
                && str_contains($data['body'], 'به وقت تهران')
                && str_contains($data['body'], 'شارژ ناقص')
                && str_contains($data['url'], 'wallet_topup=1');
        });
        $this->assertSame('خاموش ـ موجودی ناکافی', $vm->fresh()->customerStatusLabel());
    }

    public function test_deadline_queues_deletion_and_caps_vm_disk_backup_and_pending_charges(): void
    {
        $owner = Customer::factory()->create();
        $owner->wallet()->update(['balance' => -10000]);
        $vm = $this->machine($owner);
        $disk = VmDisk::create(['virtual_machine_id' => $vm->id, 'disk_device' => 'scsi1', 'size_gb' => 10, 'status' => 'ready', 'last_billed_at' => now()]);
        $backup = VmBackup::create(['virtual_machine_id' => $vm->id, 'source' => 'manual', 'size_bytes' => 1073741824, 'status' => 'ready', 'last_billed_at' => now()]);
        ResourceRate::create(['resource' => ResourceRate::DISK, 'label' => 'Disk', 'unit' => 'GB', 'hourly_price' => 10, 'is_active' => true]);
        ResourceRate::create(['resource' => ResourceRate::BACKUP, 'label' => 'Backup', 'unit' => 'GB', 'hourly_price' => 10, 'is_active' => true]);
        $service = app(VmWalletRetentionService::class);
        $service->reconcile($owner, $vm);
        $this->travel(8)->days();
        $pending = app(UsageBalanceService::class)->customerPendingUsage($owner);
        $this->assertSame(35280, $pending); // 168 hours × (100 VM + 100 disk + 10 backup).
        $service->reconcile($owner, $vm->fresh());
        Queue::assertPushed(DeleteVirtualMachineJob::class, 1);
        $this->assertSame(VirtualMachine::STATUS_DELETING, $vm->fresh()->status);
        $this->travel(2)->days();
        app(UsageBillingService::class)->accrueExtraDisk($disk);
        app(UsageBillingService::class)->accrueBackup($backup);
        $this->assertSame($pending, app(UsageBalanceService::class)->customerPendingUsage($owner));
    }

    public function test_funding_before_worker_starts_cancels_automatic_deletion_without_provider_calls(): void
    {
        $owner = Customer::factory()->create();
        $owner->wallet()->update(['balance' => -10000]);
        $vm = $this->machine($owner);
        $service = app(VmWalletRetentionService::class);
        $service->reconcile($owner, $vm);
        $this->travel(7)->days();
        $service->reconcile($owner, $vm->fresh());
        $owner->wallet()->update(['balance' => 1000000]);
        $proxmox = $this->mock(ProxmoxService::class);
        $proxmox->shouldNotReceive('deleteVm');
        (new DeleteVirtualMachineJob($vm->id))->handle($proxmox, app(VirtualMachineDeletionService::class));
        $vm->refresh();
        $this->assertSame(VirtualMachine::STATUS_STOPPED, $vm->status);
        $this->assertNull($vm->wallet_retention_expires_at);
        $this->assertNull($vm->delete_requested_at);
        $this->assertNull($vm->delete_started_at);
    }

    public function test_location_stopped_price_overrides_bundle_price_including_free_retention(): void
    {
        $owner = Customer::factory()->create();
        $vm = $this->machine($owner);
        $location = InfrastructureLocation::create([
            'name' => 'Tehran', 'provider' => 'proxmox', 'is_active' => true,
        ]);
        $vm->update(['infrastructure_location_id' => $location->id]);
        VmBundleLocationMapping::create([
            'vm_bundle_id' => $vm->vm_bundle_id, 'infrastructure_location_id' => $location->id,
            'is_active' => true, 'stopped_monthly_price' => 0,
        ]);
        $this->assertSame(0.0, app(BillingService::class)->persistentHourly($vm->fresh()));
    }

    public function test_shared_workspace_uses_owner_balance_and_admin_exemption_cancels_queued_deletion(): void
    {
        $owner = Customer::factory()->create();
        $member = Customer::factory()->create();
        $owner->wallet()->update(['balance' => -10000]);
        $member->wallet()->update(['balance' => 1000000]);
        $vm = $this->machine($member, ['project_id' => $owner->ensureDefaultProject()->id]);
        $service = app(VmWalletRetentionService::class);
        $service->reconcile($owner, $vm);
        $this->assertNotNull($vm->fresh()->wallet_retention_expires_at);
        $this->travel(7)->days();
        $service->reconcile($owner, $vm->fresh());
        $owner->update(['auto_suspend_vms' => false]);
        $this->assertFalse($service->allowDeletion($vm->fresh()));
        $this->assertSame(VirtualMachine::STATUS_STOPPED, $vm->fresh()->status);
    }

    public function test_automatic_deletion_cleans_up_backups_and_keeps_financial_records(): void
    {
        $owner = Customer::factory()->create();
        $vm = $this->machine($owner);
        $backup = VmBackup::create([
            'virtual_machine_id' => $vm->id, 'source' => 'manual', 'size_bytes' => 0,
            'status' => 'ready', 'last_billed_at' => now(),
        ]);
        $service = app(VmWalletRetentionService::class);
        $service->reconcile($owner, $vm);
        $this->travel(7)->days();
        $service->reconcile($owner, $vm->fresh());
        $proxmox = $this->mock(ProxmoxService::class);
        $proxmox->shouldNotReceive('deleteVm');
        (new DeleteVirtualMachineJob($vm->id))->handle($proxmox, app(VirtualMachineDeletionService::class));
        $this->assertSame(VirtualMachine::STATUS_DELETED, $vm->fresh()->status);
        $this->assertSame(VmBackup::STATUS_DELETED, $backup->fresh()->status);
        $this->assertSame(16800, (int) UsageAccrual::query()->where('virtual_machine_id', $vm->id)->sum('amount'));
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id, 'type' => VmWalletRetentionNotification::class]);
    }

    public function test_started_automatic_delete_stays_irreversible_on_retry_after_funding(): void
    {
        $owner = Customer::factory()->create();
        $vm = $this->machine($owner, [
            'status' => VirtualMachine::STATUS_DELETING,
            'wallet_retention_expires_at' => now()->subDay(),
            'remote_state' => [
                'delete_requested' => ['actor' => VmWalletRetentionService::DELETE_ACTOR],
                'wallet_delete_started_at' => now()->subMinute()->toISOString(),
            ],
        ]);
        $owner->wallet()->update(['balance' => 1000000]);
        $this->assertTrue(app(VmWalletRetentionService::class)->allowDeletion($vm));
        $this->assertSame(VirtualMachine::STATUS_DELETING, $vm->fresh()->status);
    }

    public function test_hetzner_keeps_provider_rate_unless_a_stopped_price_is_explicit(): void
    {
        $owner = Customer::factory()->create();
        $vm = $this->machine($owner, [
            'provider' => VirtualMachine::PROVIDER_HETZNER,
            'provider_metadata' => ['hetzner_price' => ['monthly_price_irr' => 3650000]],
        ]);
        $this->assertSame(100.0, app(BillingService::class)->persistentHourly($vm));
        $vm->bundle->update(['stopped_monthly_price' => null]);
        $this->assertSame(5000.0, app(BillingService::class)->persistentHourly($vm->fresh()));
    }

    public function test_admin_can_set_retention_days_and_invalid_days_are_rejected(): void
    {
        $admin = User::factory()->create();
        $payload = [
            'wallet_alert_percentages' => '15, 10, 5', 'wallet_alert_recipient_policy' => 'owner',
            'unverified_customer_vm_limit' => 2, 'verified_customer_vm_limit' => 10,
            'deleted_vm_cooldown_days' => 7, 'vm_rebuild_fee_multiplier_percentage' => 10,
            'wallet_retention_days' => 14,
        ];
        $this->actingAs($admin, 'admin')->patch('https://admin.localhost/settings/protection', $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(14, AppSetting::customerWalletRetentionDays());
        $this->patch('https://admin.localhost/settings/protection', array_merge($payload, ['wallet_retention_days' => 0]))
            ->assertSessionHasErrors('wallet_retention_days');
        $this->assertSame(14, AppSetting::customerWalletRetentionDays());
    }

    public function test_manual_deletion_keeps_separate_backup_billing_after_old_retention_deadline(): void
    {
        $owner = Customer::factory()->create();
        $vm = $this->machine($owner, [
            'status' => VirtualMachine::STATUS_DELETED, 'deleted_at' => now()->subDays(2),
            'wallet_retention_expires_at' => now()->subDay(),
            'remote_state' => ['delete_requested' => ['actor' => 'customer']],
        ]);
        ResourceRate::create(['resource' => ResourceRate::BACKUP, 'label' => 'Backup', 'unit' => 'GB', 'hourly_price' => 10, 'is_active' => true]);
        $backup = VmBackup::create([
            'virtual_machine_id' => $vm->id, 'source' => 'manual', 'size_bytes' => 1073741824,
            'status' => 'ready', 'last_billed_at' => now()->subHour(),
        ]);
        $this->assertSame(10, app(UsageBalanceService::class)->customerPendingUsage($owner));
        app(UsageBillingService::class)->accrueBackup($backup);
        $this->assertSame(10, (int) UsageAccrual::query()->where('virtual_machine_id', $vm->id)->sum('amount'));
    }

    public function test_customer_sees_jalali_deadline_and_funding_amount_in_toman(): void
    {
        $owner = Customer::factory()->create();
        $owner->wallet()->update(['balance' => -10000]);
        $vm = $this->machine($owner);
        app(VmWalletRetentionService::class)->reconcile($owner, $vm);
        $this->actingAs($owner, 'customer')->get('https://cp.localhost/wallet?topup=1&restore=1')
            ->assertOk()->assertSee('شارژ کیف پول و حفظ سرورها')
            ->assertSee('به وقت تهران')->assertSee('شارژ ناقص مهلت را تمدید نمی‌کند')
            ->assertViewHas('restoreAmountToman', 250000);
    }
}
