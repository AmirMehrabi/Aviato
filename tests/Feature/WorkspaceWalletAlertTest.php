<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\WorkspaceWalletAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceWalletAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portals.admin.domain' => 'admin.localhost', 'portals.customer.domain' => 'cp.localhost']);
    }

    public function test_default_levels_are_15_10_and_5_and_alerts_are_idempotent(): void
    {
        [$owner, $project] = $this->billableWorkspace();
        $alerts = app(WorkspaceWalletAlertService::class);

        $this->assertSame([15, 10, 5], AppSetting::customerWalletAlertPercentages());
        $owner->wallet()->update(['balance' => 100_000]);
        $alerts->checkOwner($owner);
        $alerts->checkOwner($owner);

        $this->assertSame(1, DB::table('workspace_wallet_alert_deliveries')->where('project_id', $project->id)->count());
        $this->assertSame(15, DB::table('workspace_wallet_alert_deliveries')->value('threshold_percent'));

        $owner->wallet()->update(['balance' => 35_000]);
        $alerts->checkOwner($owner);
        $this->assertSame(2, DB::table('workspace_wallet_alert_deliveries')->count());
        $this->assertSame(5, DB::table('workspace_wallet_alert_deliveries')->latest('id')->value('threshold_percent'));

        $owner->wallet()->update(['balance' => 200_000]);
        $alerts->checkOwner($owner);
        $owner->wallet()->update(['balance' => 100_000]);
        $alerts->checkOwner($owner);
        $this->assertSame(3, DB::table('workspace_wallet_alert_deliveries')->count());
    }

    public function test_admin_can_change_global_levels_in_protection_settings(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')->patch('https://admin.localhost/settings/protection', [
            'wallet_alert_percentages' => '25, 15, 5',
            'wallet_alert_recipient_policy' => 'owner_and_billing',
            'unverified_customer_vm_limit' => 2,
            'verified_customer_vm_limit' => 0,
            'deleted_vm_cooldown_days' => 30,
            'vm_rebuild_fee_multiplier_percentage' => 50,
        ])->assertSessionHas('status');

        $this->assertSame([25, 15, 5], AppSetting::customerWalletAlertPercentages());
        $this->assertSame('owner_and_billing', AppSetting::customerWalletAlertRecipientPolicy());

        $this->actingAs($admin, 'admin')->patch('https://admin.localhost/settings/protection', [
            'wallet_alert_percentages' => '15, 15',
            'wallet_alert_recipient_policy' => 'owner',
            'unverified_customer_vm_limit' => 2,
            'verified_customer_vm_limit' => 0,
            'deleted_vm_cooldown_days' => 30,
            'vm_rebuild_fee_multiplier_percentage' => 50,
        ])->assertSessionHasErrors('wallet_alert_percentages');
    }

    public function test_scheduled_check_detects_unsettled_usage_without_a_wallet_transaction(): void
    {
        [$owner, $project] = $this->billableWorkspace();
        $owner->wallet()->update(['balance' => 110_500]);

        $this->artisan('billing:check-wallet-alerts')->assertSuccessful();
        $this->assertSame(0, DB::table('workspace_wallet_alert_deliveries')->count());

        $project->virtualMachines()->firstOrFail()->update(['last_billed_at' => now()->subHours(3)]);
        $this->artisan('billing:check-wallet-alerts')->assertSuccessful();
        $this->assertDatabaseHas('workspace_wallet_alert_deliveries', [
            'project_id' => $project->id,
            'customer_id' => $owner->id,
            'kind' => 'automatic',
            'threshold_percent' => 15,
        ]);
    }

    public function test_admin_settings_inherit_until_workspace_overrides_and_manual_send_does_not_consume_levels(): void
    {
        [$owner, $project] = $this->billableWorkspace();
        $admin = User::factory()->create();
        $member = Customer::factory()->create();
        $outsider = Customer::factory()->create();
        $project->members()->create(['customer_id' => $member->id, 'role' => 'billing']);
        $base = 'https://admin.localhost/workspaces/'.$project->uuid;

        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_ALERT_PERCENTAGES, [20, 10], 'array', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_ALERT_RECIPIENT_POLICY, 'owner_and_billing', 'string', 'billing');
        $this->assertSame([20, 10], app(WorkspaceWalletAlertService::class)->thresholds($project));
        $this->assertCount(2, app(WorkspaceWalletAlertService::class)->recipients($project));

        $this->actingAs($admin, 'admin')->patch($base.'/wallet-alerts', [
            'threshold_mode' => 'custom',
            'thresholds' => '15, 5',
            'recipient_mode' => 'custom',
            'recipient_ids' => [$member->id],
        ])->assertSessionHas('status');

        $project->refresh();
        $this->assertSame([15, 5], $project->wallet_alert_thresholds);
        $this->assertSame([$member->id], $project->wallet_alert_recipient_ids);

        $this->actingAs($admin, 'admin')->post($base.'/wallet-alerts/send')->assertSessionHas('status');
        $this->assertDatabaseHas('workspace_wallet_alert_deliveries', [
            'project_id' => $project->id,
            'customer_id' => $member->id,
            'sent_by_admin_id' => $admin->id,
            'kind' => 'manual',
        ]);
        $this->assertSame(0, DB::table('workspace_wallet_alert_states')->count());
        $this->assertSame(0, $owner->notifications()->count());
        $this->assertSame('workspace_wallet_balance', $member->notifications()->firstOrFail()->data['event']);
        $this->assertStringContainsString('٪', $member->notifications()->firstOrFail()->data['body']);

        $this->actingAs($admin, 'admin')->patch($base.'/wallet-alerts', [
            'threshold_mode' => 'default',
            'recipient_mode' => 'default',
            'recipient_ids' => [$outsider->id],
        ])->assertSessionHasErrors('recipient_ids.0');

        $this->actingAs($admin, 'admin')->patch($base.'/wallet-alerts', [
            'threshold_mode' => 'default',
            'recipient_mode' => 'default',
        ])->assertSessionHas('status');
        $this->assertNull($project->fresh()->wallet_alert_thresholds);
        $this->assertNull($project->fresh()->wallet_alert_recipient_ids);
    }

    private function billableWorkspace(): array
    {
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $bundle = VmBundle::create([
            'name' => 'Alert bundle', 'slug' => 'alert-bundle', 'cpu_cores' => 1,
            'ram_gb' => 1, 'disk_gb' => 10, 'ip_count' => 1,
            'monthly_price' => 730_000, 'hourly_price' => 1000,
            'is_active' => true, 'sort_order' => 1,
        ]);
        VirtualMachine::create([
            'customer_id' => $owner->id, 'project_id' => $project->id,
            'vm_bundle_id' => $bundle->id, 'name' => 'alert-vm',
            'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10, 'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => now(),
        ]);

        return [$owner, $project];
    }
}
