<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\CustomerWalletAlertService;
use App\Services\ProjectAccessService;
use App\Services\Sms\KavenegarLookupClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CustomerWalletRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_positive_balance_below_notification_threshold_does_not_block_navigation(): void
    {
        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 500000]);
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_THRESHOLD, 750000, 'integer', 'billing');

        $this->actingAs($customer, 'customer')
            ->get($this->customerBaseUrl.'/dashboard')
            ->assertOk();
    }

    public function test_threshold_balance_sends_notification_without_locking_wallet_access(): void
    {
        $customer = Customer::factory()->create(['phone' => '09123456789']);
        $customer->wallet()->update(['balance' => 750000]);
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_THRESHOLD, 750000, 'integer', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_ENABLED, true, 'boolean', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_TEMPLATE, 'wallet-alert', 'string', 'billing');
        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar', 'string', 'notifications');

        $client = Mockery::mock(KavenegarLookupClient::class);
        $client->shouldReceive('sendLookup')
            ->once()
            ->with('09123456789', 'wallet-alert', Mockery::type('string'));
        $this->app->instance(KavenegarLookupClient::class, $client);

        app(CustomerWalletAlertService::class)->handleWalletBalanceChange($customer);

        $this->assertDatabaseHas('wallets', [
            'customer_id' => $customer->id,
            'balance' => 750000,
            'negative_notification_count' => 1,
        ]);

        $this->actingAs($customer, 'customer')
            ->get($this->customerBaseUrl.'/dashboard')
            ->assertOk();
    }

    public function test_depleted_customer_can_open_and_switch_workspaces(): void
    {
        $customer = Customer::factory()->create();
        $otherOwner = Customer::factory()->create();
        $otherWorkspace = $otherOwner->ensureDefaultProject();
        $otherWorkspace->members()->create([
            'customer_id' => $customer->id,
            'role' => ProjectMember::ROLE_MEMBER,
        ]);
        $customer->wallet()->update(['balance' => 0]);

        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/projects')->assertOk();

        $this->post($this->customerBaseUrl.'/projects/switch', [
            'project_id' => $otherWorkspace->id,
        ])->assertSessionHas(ProjectAccessService::SESSION_KEY, $otherWorkspace->id);
    }

    public function test_protection_page_explains_active_currency_and_depletion_rule(): void
    {
        config([
            'portals.admin.domain' => 'admin.localhost',
            'portals.customer.domain' => 'cp.localhost',
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('https://admin.localhost/settings/protection')
            ->assertOk()
            ->assertSee('آستانه اعلان')
            ->assertSee('موجودی مؤثر کیف‌پول به صفر یا کمتر')
            ->assertSee('IRR');
    }
}
