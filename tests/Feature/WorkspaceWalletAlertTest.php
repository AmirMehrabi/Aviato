<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\Sms\KavenegarLookupClient;
use App\Services\WorkspaceWalletAlertService;
use App\Services\WorkspaceWalletQuietHours;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WorkspaceWalletAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portals.admin.domain' => 'admin.localhost', 'portals.customer.domain' => 'cp.localhost']);
        $this->travelTo(Carbon::parse('2026-09-28 10:00:00', 'Asia/Tehran'));
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
            'wallet_shutdown_percentage' => 12,
            'wallet_alert_recipient_policy' => 'owner_and_billing',
            'unverified_customer_vm_limit' => 2,
            'verified_customer_vm_limit' => 0,
            'deleted_vm_cooldown_days' => 30,
            'vm_rebuild_fee_multiplier_percentage' => 50,
        ])->assertSessionHas('status');

        $this->assertSame([25, 15, 5], AppSetting::customerWalletAlertPercentages());
        $this->assertSame(12, AppSetting::customerWalletShutdownPercentage());
        $this->assertSame('owner_and_billing', AppSetting::customerWalletAlertRecipientPolicy());

        $this->actingAs($admin, 'admin')->patch('https://admin.localhost/settings/protection', [
            'wallet_alert_percentages' => '15, 15',
            'wallet_alert_recipient_policy' => 'owner',
            'unverified_customer_vm_limit' => 2,
            'verified_customer_vm_limit' => 0,
            'deleted_vm_cooldown_days' => 30,
            'vm_rebuild_fee_multiplier_percentage' => 50,
        ])->assertSessionHasErrors('wallet_alert_percentages');

        $this->actingAs($admin, 'admin')->patch('https://admin.localhost/settings/protection', [
            'wallet_alert_percentages' => '15, 10, 5',
            'wallet_shutdown_percentage' => 101,
            'wallet_alert_recipient_policy' => 'owner',
            'unverified_customer_vm_limit' => 2,
            'verified_customer_vm_limit' => 0,
            'deleted_vm_cooldown_days' => 30,
            'vm_rebuild_fee_multiplier_percentage' => 50,
        ])->assertSessionHasErrors('wallet_shutdown_percentage');
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

    public function test_quiet_hours_queue_automatic_alerts_and_space_them_hourly_per_recipient(): void
    {
        [$owner, $project] = $this->billableWorkspace();
        $second = $owner->ownedProjects()->create(['name' => 'Second workspace', 'slug' => 'second-workspace']);
        $second->members()->create(['customer_id' => $owner->id, 'role' => 'owner']);
        $owner->wallet()->update(['balance' => 100_000]);

        $this->travelTo(Carbon::parse('2026-09-27 02:00:00', 'Asia/Tehran'));
        app(WorkspaceWalletAlertService::class)->checkOwner($owner);
        $this->assertSame(0, DB::table('workspace_wallet_alert_deliveries')->count());
        $this->assertSame(2, DB::table('workspace_wallet_alert_queue')->where('status', 'pending')->count());

        $this->artisan('billing:send-wallet-alerts')->assertSuccessful();
        $this->assertSame(0, $owner->notifications()->count());

        $this->travelTo(Carbon::parse('2026-09-27 08:00:00', 'Asia/Tehran'));
        $this->artisan('billing:send-wallet-alerts')->assertSuccessful();
        $this->assertSame(1, $owner->notifications()->count());

        $this->travelTo(Carbon::parse('2026-09-27 08:30:00', 'Asia/Tehran'));
        $this->artisan('billing:send-wallet-alerts')->assertSuccessful();
        $this->assertSame(1, $owner->notifications()->count());

        $this->travelTo(Carbon::parse('2026-09-27 09:00:00', 'Asia/Tehran'));
        $this->artisan('billing:send-wallet-alerts')->assertSuccessful();
        $this->assertSame(2, $owner->notifications()->count());
    }

    public function test_manual_alert_is_immediate_during_quiet_hours_and_obsolete_automatic_alert_is_canceled(): void
    {
        [$owner, $project] = $this->billableWorkspace();
        $owner->wallet()->update(['balance' => 100_000]);
        $this->travelTo(Carbon::parse('2026-09-27 02:00:00', 'Asia/Tehran'));
        app(WorkspaceWalletAlertService::class)->checkOwner($owner);

        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin')->post('https://admin.localhost/workspaces/'.$project->uuid.'/wallet-alerts/send')->assertSessionHas('status');
        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame('manual', DB::table('workspace_wallet_alert_deliveries')->value('kind'));
        $this->assertDatabaseCount('tickets', 0);

        $owner->wallet()->update(['balance' => 300_000]);
        app(WorkspaceWalletAlertService::class)->checkOwner($owner);
        $this->travelTo(Carbon::parse('2026-09-27 08:00:00', 'Asia/Tehran'));
        $this->artisan('billing:send-wallet-alerts')->assertSuccessful();
        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame('canceled', DB::table('workspace_wallet_alert_queue')->value('status'));
    }

    public function test_admin_can_set_quiet_hours_that_cross_midnight(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin')->patch('https://admin.localhost/settings/protection', [
            'wallet_alert_percentages' => '15, 10, 5',
            'wallet_alert_recipient_policy' => 'owner',
            'wallet_quiet_start' => '22:00',
            'wallet_quiet_end' => '06:00',
            'unverified_customer_vm_limit' => 2,
            'verified_customer_vm_limit' => 0,
            'deleted_vm_cooldown_days' => 30,
            'vm_rebuild_fee_multiplier_percentage' => 50,
        ])->assertSessionHas('status');

        $quiet = app(WorkspaceWalletQuietHours::class);
        $this->travelTo(Carbon::parse('2026-09-28 21:59:00', 'Asia/Tehran'));
        $this->assertFalse($quiet->isQuiet());
        $this->travelTo(Carbon::parse('2026-09-28 22:00:00', 'Asia/Tehran'));
        $this->assertTrue($quiet->isQuiet());
        $this->travelTo(Carbon::parse('2026-09-29 05:59:00', 'Asia/Tehran'));
        $this->assertTrue($quiet->isQuiet());
        $this->travelTo(Carbon::parse('2026-09-29 06:00:00', 'Asia/Tehran'));
        $this->assertFalse($quiet->isQuiet());
    }

    public function test_queued_sms_retries_without_duplicating_the_in_app_notification(): void
    {
        [$owner] = $this->billableWorkspace();
        $owner->update(['phone' => '09123456789']);
        $owner->wallet()->update(['balance' => 100_000]);
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_ENABLED, true, 'boolean', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_TEMPLATE, 'wallet-alert', 'string', 'billing');
        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar', 'string', 'sms');

        $attempts = 0;
        $sms = Mockery::mock(KavenegarLookupClient::class);
        $sms->shouldReceive('sendLookup')->twice()->andReturnUsing(function () use (&$attempts): void {
            $attempts++;
            if ($attempts === 1) {
                throw new RuntimeException('Temporary SMS failure');
            }
        });
        $this->app->instance(KavenegarLookupClient::class, $sms);

        $this->travelTo(Carbon::parse('2026-09-27 02:00:00', 'Asia/Tehran'));
        app(WorkspaceWalletAlertService::class)->checkOwner($owner);
        $this->assertSame(0, $attempts);

        $this->travelTo(Carbon::parse('2026-09-27 08:00:00', 'Asia/Tehran'));
        $this->artisan('billing:send-wallet-alerts')->assertSuccessful();
        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame('pending', DB::table('workspace_wallet_alert_queue')->value('sms_status'));

        $this->travelTo(Carbon::parse('2026-09-27 08:04:00', 'Asia/Tehran'));
        $this->artisan('billing:send-wallet-alerts')->assertSuccessful();
        $this->assertSame(1, $attempts);

        $this->travelTo(Carbon::parse('2026-09-27 08:05:00', 'Asia/Tehran'));
        $this->artisan('billing:send-wallet-alerts')->assertSuccessful();
        $this->assertSame('sent', DB::table('workspace_wallet_alert_queue')->value('sms_status'));
        $this->assertSame(1, $owner->notifications()->count());
    }

    public function test_manual_wallet_sms_bypasses_quiet_hours(): void
    {
        [$owner, $project] = $this->billableWorkspace();
        $project->update(['name' => 'تیم مالی-اصلی']);
        $owner->update(['phone' => '09123456789']);
        $owner->wallet()->update(['balance' => 1_234_567]);
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_ENABLED, true, 'boolean', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_TEMPLATE, 'wallet-alert', 'string', 'billing');
        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar', 'string', 'sms');

        $sms = Mockery::mock(KavenegarLookupClient::class);
        $sms->shouldReceive('sendLookup')->once()->with(
            '09123456789',
            'wallet-alert',
            KavenegarLookupClient::nameToken($owner->first_name ?: $owner->name),
            null,
            null,
            'تیم مالی اصلی',
            '123,457',
        )->andReturn(['messageid' => 123, 'message' => 'موجودی تیم مالی اصلی 123,457 تومان']);
        $this->app->instance(KavenegarLookupClient::class, $sms);
        $this->travelTo(Carbon::parse('2026-09-27 02:00:00', 'Asia/Tehran'));

        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin')->post('https://admin.localhost/workspaces/'.$project->uuid.'/wallet-alerts/send')->assertSessionHas('status');

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame(0, DB::table('workspace_wallet_alert_queue')->count());
    }

    public function test_wallet_sms_logs_the_message_returned_by_kavenegar(): void
    {
        [$owner, $project] = $this->billableWorkspace();
        $owner->update(['phone' => '09123456789']);
        $owner->wallet()->update(['balance' => 31_180_840]);
        $project->update(['name' => 'فضای کاری جدید']);
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_ENABLED, true, 'boolean', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_TEMPLATE, 'wallet-alert', 'string', 'billing');
        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar', 'string', 'sms');
        AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, 'test-key', 'string', 'sms');

        Http::fake(['api.kavenegar.com/*' => Http::response([
            'return' => ['status' => 200, 'message' => 'تایید شد'],
            'entries' => [['messageid' => 123, 'message' => 'موجودی فضای کاری جدید 3,118,084 تومان']],
        ])]);
        Log::spy();

        $this->assertTrue(app(WorkspaceWalletAlertService::class)->sendSmsNow($project, $owner, ['balance' => 31_051_220]));

        Http::assertSent(fn ($request): bool => $request['token10'] === 'فضای کاری جدید'
            && $request['token20'] === '3,118,084'
            && ! isset($request['token2'], $request['token3']));
        Log::shouldHaveReceived('info')->once()->with('Workspace wallet SMS notification sent.', Mockery::on(
            fn (array $context): bool => $context['project_id'] === $project->id
                && $context['wallet_balance'] === 31_180_840
                && $context['effective_balance'] === 31_051_220
                && $context['message_id'] === 123
                && $context['message'] === 'موجودی فضای کاری جدید 3,118,084 تومان'
                && $context['tokens']['token10'] === 'فضای کاری جدید'
                && $context['tokens']['token20'] === '3,118,084'
        ));
    }

    public function test_wallet_sms_failure_logs_the_submitted_tokens_and_provider_error(): void
    {
        [$owner, $project] = $this->billableWorkspace();
        $owner->update(['phone' => '09123456789']);
        $owner->wallet()->update(['balance' => -12_345]);
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_ENABLED, true, 'boolean', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_TEMPLATE, 'wallet-alert', 'string', 'billing');
        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar', 'string', 'sms');
        AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, 'test-key', 'string', 'sms');

        Http::fake(['api.kavenegar.com/*' => Http::response([
            'return' => ['status' => 431, 'message' => 'ساختار کد صحیح نمی باشد'],
        ], 400)]);
        Log::spy();

        $this->assertFalse(app(WorkspaceWalletAlertService::class)->sendSmsNow($project, $owner, ['balance' => -12_345]));

        Http::assertSent(fn ($request): bool => $request['token20'] === '-1,235'
            && ! isset($request['token2'], $request['token3']));
        Log::shouldHaveReceived('warning')->once()->with('Workspace wallet SMS notification failed.', Mockery::on(
            fn (array $context): bool => $context['project_id'] === $project->id
                && $context['tokens']['token20'] === '-1,235'
                && $context['provider_status'] === 431
                && str_contains($context['error'], 'ساختار کد صحیح نمی باشد')
                && ! isset($context['phone'], $context['api_key'])
        ));
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
