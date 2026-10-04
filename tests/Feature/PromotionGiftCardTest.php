<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\ProjectMember;
use App\Models\PromotionCampaign;
use App\Models\PromotionEvent;
use App\Models\User;
use App\Services\ProjectAccessService;
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
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, [
            'claim_mode' => PromotionCampaign::CLAIM_INSTANT,
            'credit_amount' => 2_500_000,
            'maximum_liability' => 2_500_000,
        ]);
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

    public function test_fixed_credit_code_is_only_credited_after_a_successful_payment(): void
    {
        $manager = User::factory()->create();
        $customer = Customer::factory()->create();
        $project = $customer->ensureDefaultProject();
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, [
            'credit_amount' => 500_000,
            'maximum_liability' => 500_000,
        ]);
        $code = app(PromotionService::class)->generateCodes($campaign, $manager)[0];
        $wallet = $customer->wallet()->firstOrFail();

        $this->actingAs($customer, 'customer')
            ->post('https://cp.localhost/wallet/gift-cards/redeem', ['code' => $code->encrypted_code])
            ->assertSessionHasErrors(['code' => 'این کد باید هنگام افزایش موجودی استفاده شود.']);
        $this->assertSame(0, $wallet->refresh()->balance);

        $payment = Payment::create([
            'customer_id' => $customer->id, 'wallet_id' => $wallet->id, 'provider' => 'dummy', 'type' => Payment::TYPE_TOP_UP,
            'status' => Payment::STATUS_PENDING, 'amount' => 1_000_000, 'currency' => 'IRR', 'authority' => 'FIXED-PAYMENT-1',
        ]);

        $service = app(PromotionService::class);
        $service->reserveForPayment($payment, $code->encrypted_code, $customer, $project);

        $this->assertSame('reserved', $code->refresh()->status);
        $this->assertSame(500_000, $payment->refresh()->promotion_bonus_amount);
        $this->assertSame(0, $wallet->refresh()->balance);

        $redemption = $service->completePaymentBonus($payment->refresh());

        $this->assertNotNull($redemption);
        $this->assertSame(500_000, $wallet->refresh()->balance);
        $this->assertSame('redeemed', $code->refresh()->status);
        $this->assertSame($payment->id, $redemption->payment_id);
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
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, ['claim_mode' => PromotionCampaign::CLAIM_INSTANT, 'audience' => PromotionCampaign::AUDIENCE_NEW, 'credit_amount' => 100_000, 'maximum_liability' => 100_000]);
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

    public function test_elecomp_landing_accepts_a_valid_code_and_hands_it_to_customer_portal(): void
    {
        $manager = User::factory()->create(['role' => AdminRole::Admin]);
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, [
            'audience' => PromotionCampaign::AUDIENCE_NEW,
            'credit_amount' => 500_000,
            'maximum_liability' => 500_000,
        ]);
        $code = app(PromotionService::class)->generateCodes($campaign, $manager)[0];

        $this->get('https://localhost/e')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertSee('هدیه‌ات را فعال کن');

        $this->post('https://localhost/e/claim', ['code' => strtolower(str_replace('-', ' ', $code->encrypted_code))])
            ->assertRedirect('https://cp.localhost/gift-cards/'.$campaign->public_id.'#'.rawurlencode(strtolower(str_replace('-', ' ', $code->encrypted_code))));

        $this->assertDatabaseHas('promotion_events', [
            'promotion_campaign_id' => $campaign->id,
            'promotion_code_id' => $code->id,
            'action' => 'elecomp_code_accepted',
        ]);
    }

    public function test_elecomp_claim_rejects_unavailable_codes_without_exposing_details(): void
    {
        $this->from('https://localhost/e')
            ->post('https://localhost/e/claim', ['code' => 'AVT-NOT-A-REAL-CODE'])
            ->assertRedirect('https://localhost/e')
            ->assertSessionHasErrors(['code' => 'کد هدیه معتبر یا قابل استفاده نیست.']);
    }

    public function test_preview_shows_credit_value_without_reserving_or_redeeming(): void
    {
        $manager = User::factory()->create();
        $customer = Customer::factory()->create();
        $project = $customer->ensureDefaultProject();
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, [
            'claim_mode' => PromotionCampaign::CLAIM_INSTANT, 'credit_amount' => 2_000_000,
        ]);
        $code = app(PromotionService::class)->generateCodes($campaign, $manager)[0];
        $before = $code->refresh()->getRawOriginal();
        $events = PromotionEvent::count();
        $this->actingAs($customer, 'customer')->postJson('https://cp.localhost/wallet/gift-cards/preview', [
            'code' => strtolower(str_replace('-', ' ', $code->encrypted_code)), 'project_id' => $project->id,
        ])->assertOk()->assertJsonPath('promotion.credit_amount', 2_000_000)->assertJsonPath('promotion.requires_payment', false)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertSame($before, $code->refresh()->getRawOriginal());
        $this->assertSame(0, $customer->wallet()->firstOrFail()->balance);
        $this->assertDatabaseCount('promotion_redemptions', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame($events, PromotionEvent::count());
    }

    public function test_preview_exposes_percentage_minimum_and_cap_and_fixed_payment_bonus(): void
    {
        $manager = User::factory()->create();
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');
        $percentage = $this->campaign($manager, PromotionCampaign::TYPE_PERCENTAGE, [
            'percentage' => 20, 'minimum_top_up' => 5_000_000, 'maximum_bonus' => 2_000_000,
        ]);
        $code = app(PromotionService::class)->generateCodes($percentage, $manager)[0];
        $this->postJson('https://cp.localhost/wallet/gift-cards/preview', ['code' => $code->encrypted_code])
            ->assertOk()->assertJsonPath('promotion.percentage', 20)->assertJsonPath('promotion.minimum_top_up', 5_000_000)
            ->assertJsonPath('promotion.maximum_bonus', 2_000_000)->assertJsonPath('promotion.requires_payment', true);
        $fixed = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, [
            'claim_mode' => PromotionCampaign::CLAIM_PAYMENT_REQUIRED, 'credit_amount' => 1_500_000,
        ]);
        $code = app(PromotionService::class)->generateCodes($fixed, $manager)[0];
        $this->postJson('https://cp.localhost/wallet/gift-cards/preview', ['code' => $code->encrypted_code])
            ->assertOk()->assertJsonPath('promotion.credit_amount', 1_500_000)->assertJsonPath('promotion.requires_payment', true);
    }

    public function test_preview_treats_expired_reservation_as_available_without_mutating_it(): void
    {
        $manager = User::factory()->create();
        $customer = Customer::factory()->create();
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, ['credit_amount' => 1_000_000]);
        $code = app(PromotionService::class)->generateCodes($campaign, $manager)[0];
        $code->update(['status' => 'reserved', 'reserved_until' => now()->subMinute()]);
        $this->actingAs($customer, 'customer')->postJson('https://cp.localhost/wallet/gift-cards/preview', ['code' => $code->encrypted_code])->assertOk();
        $this->assertSame('reserved', $code->refresh()->status);
        $this->assertTrue($code->reserved_until->isPast());
    }

    public function test_preview_rejects_ineligible_codes_and_requires_billing_access(): void
    {
        $manager = User::factory()->create();
        $customer = Customer::factory()->create();
        $project = $customer->ensureDefaultProject();
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, ['credit_amount' => 1_000_000]);
        $code = app(PromotionService::class)->generateCodes($campaign, $manager)[0];
        $this->actingAs($customer, 'customer');
        $url = 'https://cp.localhost/wallet/gift-cards/preview';
        $this->postJson($url, ['code' => 'INVALID'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $code->update(['status' => 'reserved', 'reserved_until' => now()->addMinute()]);
        $this->postJson($url, ['code' => $code->encrypted_code])->assertUnprocessable()->assertJsonValidationErrors('code');
        $code->update(['status' => 'available']);
        $campaign->update(['audience' => PromotionCampaign::AUDIENCE_ALLOWLIST]);
        $this->postJson($url, ['code' => $code->encrypted_code])->assertUnprocessable()->assertJsonValidationErrors('code');
        $campaign->update(['audience' => PromotionCampaign::AUDIENCE_ALL, 'expires_at' => now()->subMinute()]);
        $this->postJson($url, ['code' => $code->encrypted_code])->assertUnprocessable()->assertJsonValidationErrors('code');
        $viewer = Customer::factory()->create();
        ProjectMember::create(['project_id' => $project->id, 'customer_id' => $viewer->id, 'role' => ProjectMember::ROLE_VIEWER]);
        $this->actingAs($viewer, 'customer')->withSession([ProjectAccessService::SESSION_KEY => $project->id]);
        $this->postJson($url, ['code' => $code->encrypted_code])->assertNotFound();
    }

    public function test_preview_rate_limits_guesses_and_rejects_stale_workspace(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');
        $url = 'https://cp.localhost/wallet/gift-cards/preview';
        $this->postJson($url, ['code' => 'INVALID', 'project_id' => 99999])->assertUnprocessable()->assertJsonValidationErrors('project_id');
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson($url, ['code' => 'INVALID'])->assertUnprocessable();
        }
        $this->postJson($url, ['code' => 'INVALID'])->assertStatus(429);
        $this->assertDatabaseCount('promotion_redemptions', 0);
    }

    public function test_redeeming_revalidates_a_code_that_was_previously_previewed(): void
    {
        $manager = User::factory()->create();
        $customer = Customer::factory()->create();
        $campaign = $this->campaign($manager, PromotionCampaign::TYPE_CREDIT, [
            'claim_mode' => PromotionCampaign::CLAIM_INSTANT, 'credit_amount' => 2_000_000,
        ]);
        $code = app(PromotionService::class)->generateCodes($campaign, $manager)[0];
        $this->actingAs($customer, 'customer');
        $this->postJson('https://cp.localhost/wallet/gift-cards/preview', ['code' => $code->encrypted_code])->assertOk();
        $code->update(['status' => 'revoked']);
        $this->post('https://cp.localhost/wallet/gift-cards/redeem', ['code' => $code->encrypted_code])->assertSessionHasErrors('code');
        $this->assertSame(0, $customer->wallet()->firstOrFail()->balance);
    }

    public function test_wallet_keeps_instant_gift_entry_available_without_payment_gateways(): void
    {
        AppSetting::setValue(AppSetting::PAYMENTS_ENABLED, false, 'boolean', 'payments');
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')->get('https://cp.localhost/wallet?gift_card=1')->assertOk()
            ->assertSee('کد هدیه یا پاداش')->assertSee('بررسی کد')->assertSee('/wallet/gift-cards/redeem', false);
    }

    private function campaign(User $manager, string $type, array $overrides = []): PromotionCampaign
    {
        return PromotionCampaign::create(array_merge([
            'name' => 'کمپین تست', 'type' => $type, 'audience' => PromotionCampaign::AUDIENCE_ALL, 'status' => 'active', 'currency' => 'IRR',
            'code_count' => 1, 'maximum_liability' => 100_000, 'expires_at' => now()->addMonth(), 'created_by_id' => $manager->id,
        ], $overrides));
    }
}
