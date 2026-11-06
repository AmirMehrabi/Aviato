<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\UsageAccrual;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerWalletDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_activity_uses_jalali_month_across_gregorian_month_boundary(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 1, 12, 0));
        $customer = Customer::factory()->create();
        $this->transaction($customer, '2026-10-31 23:59:59', -2840787, 'October charge');
        $this->transaction($customer, '2026-11-01 00:00:00', -315656, 'November charge');

        $this->actingAs($customer, 'customer')->get('https://cp.localhost/wallet')
            ->assertOk()
            ->assertViewHas('monthlyCharges', 3156443)
            ->assertSee('فعالیت‌های این ماه')
            ->assertSee('1405/08/01')
            ->assertSee('1405/08/10');
    }

    public function test_wallet_activity_includes_only_transactions_inside_current_jalali_month(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 21, 23, 59, 59));
        $customer = Customer::factory()->create();
        $this->transaction($customer, '2026-10-22 23:59:59', -100, 'Previous Jalali month');
        $this->transaction($customer, '2026-10-23 00:00:00', -200, 'First second of Aban');
        $this->transaction($customer, '2026-11-21 23:59:59', -300, 'Last second of Aban');
        $this->transaction($customer, '2026-11-22 00:00:00', -400, 'Next Jalali month');
        $this->transaction($customer, '2026-10-22 23:59:59', 500, 'Previous credit');
        $this->transaction($customer, '2026-10-23 00:00:00', 600, 'Current credit');
        $this->transaction($customer, '2026-11-22 00:00:00', 700, 'Next credit');

        $this->actingAs($customer, 'customer')->get('https://cp.localhost/wallet')
            ->assertOk()
            ->assertViewHas('monthlyCharges', 500)
            ->assertViewHas('monthlyCredits', 600)
            ->assertSee('فعالیت‌های این ماه')
            ->assertSee('1405/08/01')
            ->assertSee('1405/08/30');
    }

    public function test_wallet_displays_exact_balance_and_pending_usage(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 6, 13, 42));
        $customer = Customer::factory()->create();
        $project = $customer->ownedProjects()->firstOrFail();
        $customer->wallet()->update(['balance' => 3327469]);
        UsageAccrual::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'scope_key' => 'project:'.$project->id,
            'category' => UsageAccrual::CATEGORY_VM,
            'resource_type' => 'virtual_machine',
            'resource_id' => 199,
            'service_date' => '2026-11-06',
            'period_start' => '2026-11-06 00:00:00',
            'period_end' => now(),
            'amount' => 188724,
        ]);

        foreach (['overview', 'top-up'] as $tab) {
            $this->actingAs($customer, 'customer')->get('https://cp.localhost/wallet?tab='.$tab)
                ->assertOk()
                ->assertViewHas('effectiveBalance', 3138745)
                ->assertSee('۳۱۳,۸۷۴.۵ تومان')
                ->assertSee('۳۳۲,۷۴۶.۹ تومان')
                ->assertSee('۱۸,۸۷۲.۴ تومان')
                ->assertDontSee('۳۲۰,۰۰۰ تومان');
        }
    }

    public function test_transaction_filters_and_dates_use_gregorian_calendar_and_survive_reload(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 6, 13, 42));
        $customer = Customer::factory()->create();
        $this->transaction($customer, '2026-10-31 23:59:59', -100, 'Outside range');
        $this->transaction($customer, '2026-11-01 00:00:00', -100, 'Start boundary');
        $this->transaction($customer, '2026-11-06 23:59:59', -100, 'End boundary');
        $this->transaction($customer, '2026-11-07 00:00:00', -100, 'After range');
        $query = '?from=2026-11-01&to=2026-11-06';
        $this->actingAs($customer, 'customer');

        $this->get('https://cp.localhost/wallet'.$query)
            ->assertOk()
            ->assertSee('2026-11-01 00:00')
            ->assertSee('2026-11-06 23:59')
            ->assertDontSee('Outside range')
            ->assertDontSee('After range')
            ->assertViewHas('transactionFilters', ['from' => '2026-11-01', 'to' => '2026-11-06']);

        $response = $this->getJson('https://cp.localhost/wallet/transactions'.$query)->assertOk();
        $this->assertStringContainsString('Start boundary', $response->json('html'));
        $this->assertStringContainsString('End boundary', $response->json('html'));
        $this->assertStringNotContainsString('Outside range', $response->json('html'));
        $this->assertStringNotContainsString('After range', $response->json('html'));
        $this->getJson('https://cp.localhost/wallet/transactions?from=1405/08/01')
            ->assertRedirect()->assertSessionHasErrors('from');
        $this->getJson('https://cp.localhost/wallet/transactions?from=2026-02-30')
            ->assertRedirect()->assertSessionHasErrors('from');
    }

    private function transaction(Customer $customer, string $at, int $amount, string $description): void
    {
        WalletTransaction::forceCreate([
            'wallet_id' => $customer->wallet()->firstOrFail()->id,
            'customer_id' => $customer->id,
            'type' => WalletTransaction::TYPE_CHARGE,
            'amount' => $amount,
            'balance_before' => 10000000,
            'balance_after' => 10000000 + $amount,
            'description' => $description,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
