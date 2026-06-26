<?php

namespace Tests\Feature\Reseller;

use App\Models\Customer;
use App\Models\ResellerCommission;
use App\Models\UsageSettlement;
use App\Services\ResellerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_is_calculated_for_settlement(): void
    {
        $reseller = Customer::factory()->create();
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');
        app(ResellerService::class)->assignCustomer($reseller, $customer, 'admin');

        $settlement = UsageSettlement::create([
            'customer_id' => $customer->id,
            'scope_key' => 'customer:'.$customer->id,
            'service_date' => now()->subDay()->toDateString(),
            'amount' => 100000,
        ]);

        $commission = app(ResellerService::class)->calculateCommissionForSettlement($settlement);

        $this->assertNotNull($commission);
        $this->assertEquals($reseller->id, $commission->reseller_id);
        $this->assertEquals($customer->id, $commission->customer_id);
        $this->assertEquals(10000, $commission->commission_amount);
        $this->assertEquals(10.00, $commission->commission_pct);
        $this->assertEquals(100000, $commission->settlement_amount);
        $this->assertEquals('auto_credit', $commission->payout_method);
        $this->assertEquals(ResellerCommission::STATUS_CREDITED, $commission->status);
        $this->assertNotNull($commission->wallet_transaction_id);
        $this->assertNotNull($commission->credited_at);
    }

    public function test_commission_is_not_calculated_without_reseller(): void
    {
        $customer = Customer::factory()->create();

        $settlement = UsageSettlement::create([
            'customer_id' => $customer->id,
            'scope_key' => 'customer:'.$customer->id,
            'service_date' => now()->subDay()->toDateString(),
            'amount' => 100000,
        ]);

        $commission = app(ResellerService::class)->calculateCommissionForSettlement($settlement);

        $this->assertNull($commission);
    }

    public function test_commission_is_not_calculated_for_zero_settlement(): void
    {
        $reseller = Customer::factory()->create();
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');
        app(ResellerService::class)->assignCustomer($reseller, $customer, 'admin');

        $settlement = UsageSettlement::create([
            'customer_id' => $customer->id,
            'scope_key' => 'customer:'.$customer->id,
            'service_date' => now()->subDay()->toDateString(),
            'amount' => 0,
        ]);

        $commission = app(ResellerService::class)->calculateCommissionForSettlement($settlement);

        $this->assertNull($commission);
    }

    public function test_withdrawable_commission_goes_to_earnings_balance(): void
    {
        $reseller = Customer::factory()->create();
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'withdrawable');
        app(ResellerService::class)->assignCustomer($reseller, $customer, 'admin');

        $settlement = UsageSettlement::create([
            'customer_id' => $customer->id,
            'scope_key' => 'customer:'.$customer->id,
            'service_date' => now()->subDay()->toDateString(),
            'amount' => 100000,
        ]);

        $commission = app(ResellerService::class)->calculateCommissionForSettlement($settlement);

        $this->assertNotNull($commission);
        $this->assertEquals(ResellerCommission::STATUS_PENDING, $commission->status);
        $this->assertNull($commission->wallet_transaction_id);

        $reseller->refresh();
        $this->assertEquals(10000, $reseller->reseller_earnings_balance);
    }

    public function test_auto_credit_commission_adds_to_wallet(): void
    {
        $reseller = Customer::factory()->create();
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');
        app(ResellerService::class)->assignCustomer($reseller, $customer, 'admin');

        $settlement = UsageSettlement::create([
            'customer_id' => $customer->id,
            'scope_key' => 'customer:'.$customer->id,
            'service_date' => now()->subDay()->toDateString(),
            'amount' => 100000,
        ]);

        app(ResellerService::class)->calculateCommissionForSettlement($settlement);

        $reseller->refresh();
        $wallet = $reseller->wallet;
        $this->assertEquals(10000, $wallet->balance);
    }

    public function test_commission_floor_rounding(): void
    {
        $reseller = Customer::factory()->create();
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');
        app(ResellerService::class)->assignCustomer($reseller, $customer, 'admin');

        $settlement = UsageSettlement::create([
            'customer_id' => $customer->id,
            'scope_key' => 'customer:'.$customer->id,
            'service_date' => now()->subDay()->toDateString(),
            'amount' => 99999,
        ]);

        $commission = app(ResellerService::class)->calculateCommissionForSettlement($settlement);

        $this->assertNotNull($commission);
        $this->assertEquals(9999, $commission->commission_amount);
    }
}
