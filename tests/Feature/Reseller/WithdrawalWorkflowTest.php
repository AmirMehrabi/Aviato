<?php

namespace Tests\Feature\Reseller;

use App\Models\Customer;
use App\Models\ResellerWithdrawalRequest;
use App\Models\User;
use App\Services\ResellerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WithdrawalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $adminBaseUrl = 'https://admin.localhost';

    public function test_reseller_can_request_withdrawal(): void
    {
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'withdrawable');
        $reseller->update(['reseller_earnings_balance' => 50000]);

        $request = app(ResellerService::class)->requestWithdrawal($reseller, 30000);

        $this->assertEquals(ResellerWithdrawalRequest::STATUS_PENDING, $request->status);
        $this->assertEquals(30000, $request->amount);
        $this->assertEquals($reseller->id, $request->reseller_id);
    }

    public function test_reseller_cannot_request_withdrawal_above_balance(): void
    {
        $this->expectException(ValidationException::class);
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'withdrawable');
        $reseller->update(['reseller_earnings_balance' => 50000]);

        app(ResellerService::class)->requestWithdrawal($reseller, 60000);
    }

    public function test_reseller_cannot_request_withdrawal_for_auto_credit(): void
    {
        $this->expectException(ValidationException::class);
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');
        $reseller->update(['reseller_earnings_balance' => 50000]);

        app(ResellerService::class)->requestWithdrawal($reseller, 30000);
    }

    public function test_admin_can_approve_withdrawal(): void
    {
        $admin = User::factory()->create();
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'withdrawable');
        $reseller->update(['reseller_earnings_balance' => 50000]);

        $withdrawal = ResellerWithdrawalRequest::create([
            'reseller_id' => $reseller->id,
            'amount' => 30000,
            'status' => ResellerWithdrawalRequest::STATUS_PENDING,
        ]);

        app(ResellerService::class)->approveWithdrawal($withdrawal, $admin, 'تایید شد');

        $withdrawal->refresh();
        $reseller->refresh();

        $this->assertEquals(ResellerWithdrawalRequest::STATUS_APPROVED, $withdrawal->status);
        $this->assertEquals('تایید شد', $withdrawal->admin_note);
        $this->assertEquals($admin->id, $withdrawal->processed_by_user_id);
        $this->assertNotNull($withdrawal->processed_at);
        $this->assertEquals(20000, $reseller->reseller_earnings_balance);
    }

    public function test_admin_can_reject_withdrawal(): void
    {
        $admin = User::factory()->create();
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'withdrawable');
        $reseller->update(['reseller_earnings_balance' => 50000]);

        $withdrawal = ResellerWithdrawalRequest::create([
            'reseller_id' => $reseller->id,
            'amount' => 30000,
            'status' => ResellerWithdrawalRequest::STATUS_PENDING,
        ]);

        app(ResellerService::class)->rejectWithdrawal($withdrawal, $admin, 'موجودی کافی نیست');

        $withdrawal->refresh();
        $reseller->refresh();

        $this->assertEquals(ResellerWithdrawalRequest::STATUS_REJECTED, $withdrawal->status);
        $this->assertEquals('موجودی کافی نیست', $withdrawal->admin_note);
        $this->assertEquals(50000, $reseller->reseller_earnings_balance);
    }

    public function test_admin_can_mark_withdrawal_as_paid(): void
    {
        $admin = User::factory()->create();
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'withdrawable');

        $withdrawal = ResellerWithdrawalRequest::create([
            'reseller_id' => $reseller->id,
            'amount' => 30000,
            'status' => ResellerWithdrawalRequest::STATUS_APPROVED,
        ]);

        app(ResellerService::class)->markWithdrawalPaid($withdrawal, $admin);

        $withdrawal->refresh();

        $this->assertEquals(ResellerWithdrawalRequest::STATUS_PAID, $withdrawal->status);
    }

    public function test_admin_can_manage_withdrawals_from_admin_panel(): void
    {
        $admin = User::factory()->create();
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'withdrawable');
        $reseller->update(['reseller_earnings_balance' => 50000]);

        $this->actingAs($admin, 'admin');

        $this->get($this->adminBaseUrl.'/resellers/withdrawals')->assertOk();
    }
}
