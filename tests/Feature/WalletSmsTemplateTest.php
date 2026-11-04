<?php

namespace Tests\Feature;

use App\Jobs\SendWalletLifecycleSms;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\VirtualMachine;
use App\Services\WalletLifecycleNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalletSmsTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_only_adds_missing_templates_and_never_changes_active_settings(): void
    {
        AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, 'fake');
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_TEMPLATE, 'aviato-negative-credit');
        Http::fake([
            '*/templatelist.json*' => Http::response(['return' => ['status' => 200], 'entries' => [['name' => 'aviato-credit-days-v2', 'id' => 42]]]),
            '*/gettemplate.json*' => Http::response(['return' => ['status' => 200], 'entries' => ['name' => 'aviato-credit-days-v2', 'approvalstatus' => 'PendingReview']]),
            '*/addtemplate.json' => Http::response(['return' => ['status' => 200]]),
        ]);
        $this->artisan('wallet:configure-sms --apply')->assertSuccessful();
        Http::assertSentCount(5);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'updatetemplate'));
        $this->assertSame('aviato-negative-credit', AppSetting::customerWalletNegativeSmsTemplate());
        $this->assertNull(AppSetting::getValue('customer.wallet.days_sms_template'));
    }

    public function test_lifecycle_sms_is_disabled_by_default_and_deduplicated_per_cycle_when_enabled(): void
    {
        Queue::fake();
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $vm = VirtualMachine::create([
            'customer_id' => $owner->id, 'project_id' => $project->id, 'name' => 'test-server',
            'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10, 'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING, 'provisioning_status' => VirtualMachine::PROVISION_READY,
            'wallet_restriction' => ['created_at' => now()->toISOString(), 'network_frozen_at' => now()->toISOString()],
        ]);
        $service = app(WalletLifecycleNotificationService::class);
        $service->notify($vm, 'frozen');
        Queue::assertNotPushed(SendWalletLifecycleSms::class);
        AppSetting::setValue('customer.wallet.frozen_sms_template', 'aviato-credit-network-v2');
        $service->notify($vm, 'frozen');
        $service->notify($vm, 'frozen');
        Queue::assertPushed(SendWalletLifecycleSms::class, 1);
    }
}
