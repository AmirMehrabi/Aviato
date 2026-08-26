<?php

namespace Tests\Feature\Reseller;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\ResellerCustomer;
use App\Services\ResellerService;
use App\Services\Sms\VerificationSmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReferralRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_reseller_signup_link_is_absolute_and_registration_form_preserves_code(): void
    {
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');

        $this->get($this->customerBaseUrl.'/register?ref='.$reseller->reseller_code)
            ->assertOk()
            ->assertSee('name="ref" value="'.$reseller->reseller_code.'"', false);

        $this->actingAs($reseller, 'customer')
            ->get($this->customerBaseUrl.'/reseller/referral')
            ->assertOk()
            ->assertSee($this->customerBaseUrl.'/register?ref='.$reseller->reseller_code)
            ->assertSee('@click="copyReferral()"', false)
            ->assertSee('navigator.clipboard.writeText(value)', false)
            ->assertDontSee('x-clipboard', false);
    }

    public function test_referral_link_uses_current_portal_origin_and_configured_registration_path(): void
    {
        config()->set('portals.customer.register_path', 'join');
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');

        $this->actingAs($reseller, 'customer')
            ->get($this->customerBaseUrl.'/reseller/referral')
            ->assertOk()
            ->assertSee($this->customerBaseUrl.'/join?ref='.$reseller->reseller_code);
    }

    public function test_registration_without_verification_immediately_assigns_referred_customer(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'disabled');
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');

        $this->post($this->customerBaseUrl.'/register', [
            'first_name' => 'Referred',
            'last_name' => 'Customer',
            'email' => 'referred@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'ref' => $reseller->reseller_code,
        ])->assertRedirect($this->customerBaseUrl.'/dashboard');

        $customer = Customer::query()->where('email', 'referred@example.com')->firstOrFail();
        $this->assertDatabaseHas('reseller_customers', [
            'reseller_id' => $reseller->id,
            'customer_id' => $customer->id,
            'assigned_via' => 'referral',
            'unassigned_at' => null,
        ]);
    }

    public function test_email_registration_assigns_referred_customer_only_after_verification(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'email');
        Mail::fake();
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');

        $this->post($this->customerBaseUrl.'/register', [
            'first_name' => 'Referred',
            'last_name' => 'Customer',
            'email' => 'verified-referral@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'ref' => $reseller->reseller_code,
        ])->assertRedirect($this->customerBaseUrl.'/email/verify?email=verified-referral%40example.com');

        $customer = Customer::query()->where('email', 'verified-referral@example.com')->firstOrFail();
        $this->assertDatabaseCount('reseller_customers', 0);

        $customer->forceFill([
            'email_verification_code' => Hash::make('123456'),
            'email_verification_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->post($this->customerBaseUrl.'/email/verify', [
            'email' => $customer->email,
            'code' => '123456',
        ])->assertRedirect($this->customerBaseUrl.'/dashboard');

        $this->assertDatabaseHas('reseller_customers', [
            'reseller_id' => $reseller->id,
            'customer_id' => $customer->id,
            'assigned_via' => 'referral',
        ]);
    }

    public function test_sms_registration_assigns_referred_customer_only_after_verification(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'sms');
        $this->mock(VerificationSmsSender::class, function ($mock): void {
            $mock->shouldReceive('send')->once();
        });
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');

        $this->post($this->customerBaseUrl.'/register', [
            'first_name' => 'Sms',
            'last_name' => 'Referral',
            'phone' => '09123456789',
            'password' => 'password',
            'password_confirmation' => 'password',
            'ref' => $reseller->reseller_code,
        ])->assertRedirect($this->customerBaseUrl.'/email/verify?phone=09123456789');

        $customer = Customer::query()->where('phone', '09123456789')->firstOrFail();
        $this->assertDatabaseCount('reseller_customers', 0);
        $customer->forceFill([
            'email_verification_code' => Hash::make('123456'),
            'email_verification_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->post($this->customerBaseUrl.'/email/verify', [
            'phone' => $customer->phone,
            'code' => '123456',
        ])->assertRedirect($this->customerBaseUrl.'/dashboard');

        $this->assertDatabaseHas('reseller_customers', [
            'reseller_id' => $reseller->id,
            'customer_id' => $customer->id,
            'assigned_via' => 'referral',
        ]);
    }

    public function test_pending_referral_is_not_applied_to_a_different_customer(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'email');
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');
        $intendedCustomer = Customer::factory()->unverified()->create();
        $otherCustomer = Customer::factory()->unverified()->create([
            'email' => 'other@example.com',
            'email_verification_code' => Hash::make('123456'),
            'email_verification_expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession(['pending_referral' => [
            'customer_id' => $intendedCustomer->id,
            'code' => $reseller->reseller_code,
        ]])->post($this->customerBaseUrl.'/email/verify', [
            'email' => $otherCustomer->email,
            'code' => '123456',
        ])->assertRedirect($this->customerBaseUrl.'/dashboard');

        $this->assertDatabaseCount('reseller_customers', 0);
    }

    public function test_registration_validation_retains_referral_code(): void
    {
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');

        $this->from($this->customerBaseUrl.'/register?ref='.$reseller->reseller_code)
            ->post($this->customerBaseUrl.'/register', ['ref' => $reseller->reseller_code])
            ->assertRedirect($this->customerBaseUrl.'/register?ref='.$reseller->reseller_code)
            ->assertSessionHasInput('ref', $reseller->reseller_code);
    }

    public function test_handle_referral_registration_creates_assignment(): void
    {
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');
        $customer = Customer::factory()->create();

        app(ResellerService::class)->handleReferralRegistration($customer, $reseller->reseller_code);

        $this->assertDatabaseHas('reseller_customers', [
            'reseller_id' => $reseller->id,
            'customer_id' => $customer->id,
            'assigned_via' => 'referral',
        ]);
    }

    public function test_handle_referral_registration_ignores_invalid_code(): void
    {
        $customer = Customer::factory()->create();

        app(ResellerService::class)->handleReferralRegistration($customer, 'INVALID');

        $this->assertDatabaseCount('reseller_customers', 0);
    }

    public function test_handle_referral_registration_ignores_inactive_reseller(): void
    {
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');
        $reseller->update(['reseller_status' => 'suspended']);

        $customer = Customer::factory()->create();
        app(ResellerService::class)->handleReferralRegistration($customer, $reseller->reseller_code);

        $this->assertDatabaseCount('reseller_customers', 0);
    }

    public function test_handle_referral_registration_replaces_previous_reseller(): void
    {
        $reseller1 = Customer::factory()->create();
        $reseller2 = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller1, 10.00, 'auto_credit');
        app(ResellerService::class)->enableReseller($reseller2, 15.00, 'auto_credit');

        $customer = Customer::factory()->create();
        app(ResellerService::class)->assignCustomer($reseller1, $customer, 'admin');
        app(ResellerService::class)->handleReferralRegistration($customer, $reseller2->reseller_code);

        $this->assertDatabaseHas('reseller_customers', [
            'reseller_id' => $reseller2->id,
            'customer_id' => $customer->id,
            'assigned_via' => 'referral',
            'unassigned_at' => null,
        ]);

        $oldAssignment = ResellerCustomer::where('reseller_id', $reseller1->id)->where('customer_id', $customer->id)->first();
        $this->assertNotNull($oldAssignment->unassigned_at);
    }
}
