<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PromotionCampaign;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionGiftCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'promotions.code_pepper' => 'test-promotion-pepper',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);
    }

    public function test_only_authorized_admin_can_manage_promotions(): void
    {
        $ordinary = User::factory()->create(['role' => AdminRole::Accountant]);
        $manager = User::factory()->create(['role' => AdminRole::Admin]);

        $this->actingAs($ordinary, 'admin')->get('https://admin.localhost/billing/promotions')->assertForbidden();
        $this->actingAs($manager, 'admin')->get('https://admin.localhost/billing/promotions')->assertOk();
    }

    public function test_legacy_super_admin_permission_endpoint_does_not_exist(): void
    {
        $admin = User::factory()->create(['role' => AdminRole::Admin]);
        $target = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->patch('https://admin.localhost/promotion-admins/'.$target->id, ['can_manage_promotions' => 1])
            ->assertNotFound();
    }

    public function test_credit_code_is_encrypted_and_redeemed_once_into_active_workspace_wallet(): void
    {
        $manager = User::factory()->create(['role' => AdminRole::Admin]);
        $customer = Customer::factory()->create();
        $project = $customer->ensureDefaultProject();
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, ['credit_amount' => 2_500_000, 'maximum_liability' => 2_500_000]);
        $codes = app(PromotionService::class)->generateCodes($campaign, $manager);
        $plain = $codes[0]->encrypted_code;

        $this->assertStringStartsWith('AVT-', $plain);
        $this->assertDatabaseMissing('promotion_codes', ['encrypted_code' => $plain]);

        $this->actingAs($customer, 'customer')
            ->post('https://cp.localhost/wallet/gift-cards/redeem', ['code' => strtolower(str_replace('-', ' ', $plain))])
            ->assertRedirect('https://cp.localhost/wallet');

        $this->assertSame(2_500_000, $customer->wallet()->firstOrFail()->balance);
        $this->assertDatabaseHas('promotion_redemptions', ['promotion_campaign_id' => $campaign->id, 'customer_id' => $customer->id, 'benefit_amount' => 2_500_000]);

        $this->post('https://cp.localhost/wallet/gift-cards/redeem', ['code' => $plain])->assertSessionHasErrors('code');
        $this->assertSame(2_500_000, $customer->wallet()->firstOrFail()->balance);
    }

    public function test_percentage_code_reserves_and_credits_separate_bonus_once(): void
    {
        $manager = User::factory()->create();
        $customer = Customer::factory()->create();
        $project = $customer->ensureDefaultProject();
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_PERCENTAGE, [
            'percentage' => 20, 'minimum_top_up' => 1_000_000, 'maximum_bonus' => 500_000, 'maximum_liability' => 500_000,
        ]);
        $code = app(PromotionService::class)->generateCodes($campaign, $manager)[0];
        $wallet = $customer->wallet()->firstOrFail();
        $payment = Payment::create([
            'customer_id' => $customer->id, 'wallet_id' => $wallet->id, 'provider' => 'dummy', 'type' => Payment::TYPE_TOP_UP,
            'status' => Payment::STATUS_PENDING, 'amount' => 4_000_000, 'currency' => 'IRR', 'authority' => 'PROMO-PAYMENT-1',
        ]);

        $service = app(PromotionService::class);
        $service->reserveForPayment($payment, $code->encrypted_code, $customer, $project);
        $this->assertSame('reserved', $code->refresh()->status);
        $this->assertSame(500_000, $payment->refresh()->promotion_bonus_amount);

        $redemption = $service->completePaymentBonus($payment->refresh());
        $this->assertNotNull($redemption);
        $this->assertSame(500_000, $wallet->refresh()->balance);
        $this->assertNull($service->completePaymentBonus($payment->refresh()));
        $this->assertSame(500_000, $wallet->refresh()->balance);
    }

    public function test_expired_reservation_is_released_for_retry(): void
    {
        $manager = User::factory()->create();
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_PERCENTAGE, ['percentage' => 10, 'minimum_top_up' => 1_000_000, 'maximum_bonus' => 100_000, 'maximum_liability' => 100_000]);
        $code = app(PromotionService::class)->generateCodes($campaign, $manager)[0];
        $code->forceFill(['status' => 'reserved', 'reserved_until' => now()->subMinute(), 'reserved_payment_id' => 999])->save();

        $this->assertSame(1, app(PromotionService::class)->releaseExpiredReservations());
        $this->assertSame('available', $code->refresh()->status);
        $this->assertNull($code->reserved_payment_id);
    }

    public function test_new_customer_campaign_rejects_wallet_with_successful_funding(): void
    {
        $manager = User::factory()->create();
        $customer = Customer::factory()->create();
        $wallet = $customer->wallet()->firstOrFail();
        Payment::create(['customer_id' => $customer->id, 'wallet_id' => $wallet->id, 'provider' => 'dummy', 'type' => Payment::TYPE_TOP_UP, 'status' => Payment::STATUS_SUCCESSFUL, 'amount' => 1_000_000, 'currency' => 'IRR', 'authority' => 'FUNDED-1', 'paid_at' => now()]);
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, ['audience' => PromotionCampaign::AUDIENCE_NEW, 'credit_amount' => 100_000, 'maximum_liability' => 100_000]);
        $code = app(PromotionService::class)->generateCodes($campaign, $manager)[0];

        $this->actingAs($customer, 'customer')->post('https://cp.localhost/wallet/gift-cards/redeem', ['code' => $code->encrypted_code])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('promotion_redemptions', 0);
    }

    public function test_sensitive_promotion_pages_are_not_cached_and_print_qr_codes(): void
    {
        $manager = User::factory()->create(['role' => AdminRole::Admin]);
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, [
            'credit_amount' => 100_000,
            'maximum_liability' => 100_000,
        ]);
        $code = app(PromotionService::class)->generateCodes($campaign, $manager)[0];

        $this->get('https://cp.localhost/gift-cards/'.$campaign->public_id.'#'.$code->encrypted_code)
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->actingAs($manager, 'admin')
            ->get('https://admin.localhost/billing/promotions/'.$campaign->public_id.'/print')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertSee('<svg', false)
            ->assertSee($code->encrypted_code);
    }

    private function campaign(User $manager, string $type, array $overrides = []): PromotionCampaign
    {
        return PromotionCampaign::create(array_merge([
            'name' => 'کمپین تست', 'type' => $type, 'audience' => PromotionCampaign::AUDIENCE_ALL, 'status' => 'active', 'currency' => 'IRR',
            'code_count' => 1, 'maximum_liability' => 100_000, 'expires_at' => now()->addMonth(), 'created_by_id' => $manager->id,
        ], $overrides));
    }
}
