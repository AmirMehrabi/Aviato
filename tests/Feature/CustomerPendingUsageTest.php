<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\UsageAccrual;
use App\Services\ProjectAccessService;
use App\Services\UsageBalanceService;
use App\Services\UsageBillingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPendingUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_only_available_balance_before_settlement(): void
    {
        $this->freezeTime();
        $owner = $this->customerWithPendingNetworkUsage();
        $wallets = app(WalletService::class);

        $this->actingAs($owner, 'customer')
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertViewHas('effectiveBalance', 80000)
            ->assertSee('موجودی قابل استفاده')
            ->assertDontSee('کارکرد تسویه‌نشده')
            ->assertDontSee('موجودی ثبت‌شده')
            ->assertDontSee($wallets->format(500000))
            ->assertSee($wallets->formatAvailableBalance(80000));

        $this->get('https://cp.localhost/wallet')
            ->assertOk()
            ->assertViewHas('effectiveBalance', 80000)
            ->assertSeeInOrder([
                'موجودی قابل استفاده', $wallets->formatAvailableBalance(80000),
            ])
            ->assertDontSee('کارکرد تسویه‌نشده')
            ->assertDontSee('موجودی ثبت‌شده')
            ->assertDontSee($wallets->format(420000))
            ->assertDontSee($wallets->format(500000));
    }

    public function test_settlement_clears_pending_usage_without_reducing_available_balance_again(): void
    {
        $this->freezeTime();
        $owner = $this->customerWithPendingNetworkUsage();
        $balances = app(UsageBalanceService::class);
        $this->assertSame(80000, $balances->effectiveBalance($owner));

        app(UsageBillingService::class)->settleDate(now()->toDateString());

        $this->assertSame(80000, $owner->wallet()->firstOrFail()->balance);
        $this->assertSame(0, $balances->customerPendingUsage($owner));
        $this->assertSame(80000, $balances->effectiveBalance($owner));
        $this->actingAs($owner, 'customer')
            ->get('https://cp.localhost/wallet')
            ->assertOk()
            ->assertViewHas('effectiveBalance', 80000);
    }

    public function test_pending_usage_warns_customer_even_when_recorded_wallet_balance_is_positive(): void
    {
        $owner = $this->customerWithPendingNetworkUsage();
        $owner->wallet()->update(['balance' => 400000]);

        $this->actingAs($owner, 'customer')
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertViewHas('effectiveBalance', -20000)
            ->assertSee('موجودی کیف پول منفی است')
            ->assertSee(app(WalletService::class)->formatAvailableBalance(-20000));
    }

    public function test_workspace_admin_sees_owners_available_balance_instead_of_their_own(): void
    {
        $owner = $this->customerWithPendingNetworkUsage();
        $admin = Customer::factory()->create();
        $admin->wallet()->update(['balance' => 900000]);
        $project = $owner->ensureDefaultProject();
        $project->members()->create(['customer_id' => $admin->id, 'role' => ProjectMember::ROLE_ADMIN]);

        $this->actingAs($admin, 'customer')
            ->withSession([ProjectAccessService::SESSION_KEY => $project->id])
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertViewHas('effectiveBalance', 80000)
            ->assertSee(app(WalletService::class)->formatAvailableBalance(80000))
            ->assertDontSee(app(WalletService::class)->format(900000));
    }

    public function test_pending_balance_is_hidden_from_members_without_billing_access(): void
    {
        $owner = $this->customerWithPendingNetworkUsage();
        $member = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $project->members()->create(['customer_id' => $member->id, 'role' => ProjectMember::ROLE_MEMBER]);

        $this->actingAs($member, 'customer')
            ->withSession([ProjectAccessService::SESSION_KEY => $project->id])
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertDontSee('کارکرد تسویه‌نشده')
            ->assertDontSee(app(WalletService::class)->formatAvailableBalance(80000));
    }

    private function customerWithPendingNetworkUsage(): Customer
    {
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $owner->wallet()->update(['balance' => 500000]);
        UsageAccrual::create([
            'customer_id' => $owner->id,
            'project_id' => $project->id,
            'scope_key' => 'project:'.$project->id,
            'category' => UsageAccrual::CATEGORY_NETWORK,
            'resource_type' => 'network_usage_bucket',
            'resource_id' => 1,
            'service_date' => now()->toDateString(),
            'period_start' => now()->startOfDay(),
            'period_end' => now(),
            'amount' => 420000,
        ]);

        return $owner;
    }
}
