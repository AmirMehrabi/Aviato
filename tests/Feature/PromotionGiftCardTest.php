<?php

namespace Tests\Feature;

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
        $ordinary = User::factory()->create();
        $manager = User::factory()->create(['can_manage_promotions' => true]);

        $this->actingAs($ordinary, 'admin')->get('https://admin.aviato.ir/billing/promotions')->assertForbidden();
        $this->actingAs($manager, 'admin')->get('https://admin.aviato.ir/billing/promotions')->assertOk();
    }

    public function test_super_admin_can_assign_promotion_permission(): void
    {
        $super = User::factory()->create(['email' => 'owner@example.com']);
        $target = User::factory()->create();
        config(['promotions.super_admin_emails' => ['owner@example.com']]);

        $this->actingAs($super, 'admin')
            ->patch('https://admin.aviato.ir/promotion-admins/'.$target->id, ['can_manage_promotions' => 1])
            ->assertRedirect();

        $this->assertTrue($target->refresh()->can_manage_promotions);
        $this->assertDatabaseHas('promotion_events', ['action' => 'permission_updated', 'user_id' => $super->id]);
    }

    public function test_credit_code_is_encrypted_and_redeemed_once_into_active_workspace_wallet(): void
    {
        $manager = User::factory()->create(['can_manage_promotions' => true]);
        $customer = Customer::factory()->create();
        $project = $customer->ensureDefaultProject();
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, ['credit_amount' => 2_500_000, 'maximum_liability' => 2_500_000]);
        $codes = app(PromotionService::class)->generateCodes($campaign, $manager);
        $plain = $codes[0]->encrypted_code;

        $this->assertStringStartsWith('AVT-', $plain);
        $this->assertDatabaseMissing('promotion_codes', ['encrypted_code' => $plain]);

        $this->actingAs($customer, 'customer')
            ->post('https://cp.aviato.ir/wallet/gift-cards/redeem', ['code' => strtolower(str_replace('-', ' ', $plain))])
            ->assertRedirect('https://cp.aviato.ir/wallet');

        $this->assertSame(2_500_000, $customer->wallet()->firstOrFail()->balance);
        $this->assertDatabaseHas('promotion_redemptions', ['promotion_campaign_id' => $campaign->id, 'customer_id' => $customer->id, 'benefit_amount' => 2_500_000]);

        $this->post('https://cp.aviato.ir/wallet/gift-cards/redeem', ['code' => $plain])->assertSessionHasErrors('code');
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

        $this->actingAs($customer, 'customer')->post('https://cp.aviato.ir/wallet/gift-cards/redeem', ['code' => $code->encrypted_code])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('promotion_redemptions', 0);
    }

    public function test_sensitive_promotion_pages_are_not_cached_and_print_qr_codes(): void
    {
        $manager = User::factory()->create(['can_manage_promotions' => true]);
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, [
            'credit_amount' => 100_000,
            'maximum_liability' => 100_000,
        ]);
        $code = app(PromotionService::class)->generateCodes($campaign, $manager)[0];

        $this->get('https://cp.aviato.ir/gift-cards/'.$campaign->public_id.'#'.$code->encrypted_code)
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->actingAs($manager, 'admin')
            ->get('https://admin.aviato.ir/billing/promotions/'.$campaign->public_id.'/print')
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
