<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\CustomerWalletAlertService;
use App\Services\ProjectAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerWalletRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_new_customer_with_empty_wallet_can_explore_the_portal(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/dashboard')
            ->assertOk()
            ->assertSee('کیف پول شما خالی است');
        $this->get($this->customerBaseUrl.'/profile')->assertOk();
        $this->get($this->customerBaseUrl.'/servers/create')->assertOk();
        $this->get($this->customerBaseUrl.'/tickets')->assertOk();
    }

    public function test_negative_wallet_can_still_open_dashboard(): void
    {
        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => -1000]);

        $this->actingAs($customer, 'customer')
            ->get($this->customerBaseUrl.'/dashboard')
            ->assertOk()
            ->assertSee('موجودی کیف پول منفی است');
    }

    public function test_positive_balance_does_not_block_navigation(): void
    {
        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 500000]);

        $this->actingAs($customer, 'customer')
            ->get($this->customerBaseUrl.'/dashboard')
            ->assertOk();
    }

    public function test_positive_wallet_balance_does_not_lock_wallet_access(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'علی رضایی',
            'first_name' => '',
            'phone' => '09123456789',
        ]);
        $customer->wallet()->update(['balance' => 750000]);
        app(CustomerWalletAlertService::class)->handleWalletBalanceChange($customer);

        $this->assertDatabaseHas('wallets', [
            'customer_id' => $customer->id,
            'balance' => 750000,
            'negative_notification_count' => 0,
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
            ->assertSee('هشدار موجودی کیف‌پول')
            ->assertSee('موجودی مؤثر کیف‌پول به صفر یا کمتر')
            ->assertSee('15, 10, 5');
    }
}
