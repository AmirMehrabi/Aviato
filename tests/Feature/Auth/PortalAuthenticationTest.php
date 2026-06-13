<?php

namespace Tests\Feature\Auth;

use App\Mail\CustomerPasswordResetCodeMail;
use App\Mail\CustomerVerificationCodeMail;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PortalAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_register_requires_email_verification_before_login(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'email');
        Mail::fake();

        $response = $this->post('https://cp.aviato.ir/register', [
            'name' => 'Customer User',
            'email' => 'customer@example.com',
            'phone' => '+15551234567',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('https://cp.aviato.ir/email/verify?email=customer%40example.com');
        $this->assertGuest('customer');
        $this->assertDatabaseHas('customers', [
            'phone' => '+15551234567',
            'email' => 'customer@example.com',
            'email_verified_at' => null,
        ]);

        $customer = Customer::query()->where('email', 'customer@example.com')->firstOrFail();
        $this->assertNotNull($customer->email_verification_code);
        $this->assertNotNull($customer->email_verification_expires_at);

        Mail::assertSent(CustomerVerificationCodeMail::class);
    }

    public function test_customer_can_verify_email_code_and_login(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'email');

        $customer = Customer::factory()->unverified()->create([
            'email' => 'customer@example.com',
            'email_verification_code' => Hash::make('123456'),
            'email_verification_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->post('https://cp.aviato.ir/email/verify', [
            'email' => 'customer@example.com',
            'code' => '123456',
        ]);

        $response->assertRedirect('https://cp.aviato.ir/dashboard');
        $this->assertAuthenticatedAs($customer, 'customer');
        $customer->refresh();
        $this->assertNotNull($customer->email_verified_at);
        $this->assertNull($customer->email_verification_code);
        $this->assertNull($customer->email_verification_expires_at);
    }

    public function test_unverified_customer_cannot_login_until_email_is_verified(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'email');

        Customer::factory()->unverified()->create([
            'email' => 'customer@example.com',
            'phone' => null,
            'password' => 'password',
        ]);

        $this->post('https://cp.aviato.ir/login', [
            'login' => 'customer@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest('customer');
    }

    public function test_admin_can_register_with_email_on_admin_subdomain(): void
    {
        $response = $this->post('https://admin.aviato.ir/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('https://admin.aviato.ir/dashboard');
        $this->assertAuthenticated('admin');
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'phone' => null,
        ]);
    }

    public function test_authenticated_admin_redirects_from_public_portal_entry_points_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');

        foreach (['/', '/login', '/register'] as $path) {
            $this->get('https://admin.aviato.ir'.$path)
                ->assertRedirect('https://admin.aviato.ir/dashboard');
        }
    }

    public function test_authenticated_customer_redirects_from_public_portal_entry_points_to_dashboard(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        foreach (['/', '/login', '/register'] as $path) {
            $this->get('https://cp.aviato.ir'.$path)
                ->assertRedirect('https://cp.aviato.ir/dashboard');
        }
    }

    public function test_customer_can_login_with_email_on_customer_subdomain(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'disabled');

        Customer::factory()->create([
            'email' => 'customer@example.com',
            'phone' => null,
            'password' => 'password',
        ]);

        $response = $this->post('https://cp.aviato.ir/login', [
            'login' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('https://cp.aviato.ir/dashboard');
        $this->assertAuthenticated('customer');
    }

    public function test_customer_can_login_even_when_their_wallet_is_negative(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'disabled');

        $customer = Customer::factory()->create([
            'email' => 'customer@example.com',
            'phone' => null,
            'password' => 'password',
        ]);

        app(WalletService::class)->charge($customer, 1000, 'کسر آزمایشی');

        $response = $this->post('https://cp.aviato.ir/login', [
            'login' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('https://cp.aviato.ir/dashboard');
        $this->assertAuthenticated('customer');
    }

    public function test_admin_and_customer_use_separate_auth_models(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'disabled');

        Customer::factory()->create([
            'phone' => '+15557654321',
            'email' => null,
            'password' => 'password',
        ]);

        User::factory()->create([
            'phone' => '+15550001111',
            'email' => null,
            'password' => 'password',
        ]);

        $this->post('https://admin.aviato.ir/login', [
            'login' => '+15557654321',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $response = $this->post('https://admin.aviato.ir/login', [
            'login' => '+15550001111',
            'password' => 'password',
        ]);

        $response->assertRedirect('https://admin.aviato.ir/dashboard');
        $this->assertAuthenticated('admin');
    }

    public function test_customer_sms_mode_requires_phone_and_redirects_to_verification(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'sms');

        $response = $this->post('https://cp.aviato.ir/register', [
            'name' => 'Sms Customer',
            'phone' => '09123456789',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('https://cp.aviato.ir/email/verify?phone=09123456789');
    }

    public function test_customer_login_page_links_to_customer_password_reset(): void
    {
        $this->get('https://cp.localhost/login')
            ->assertOk()
            ->assertSee('فراموشی رمز عبور؟')
            ->assertSee('/password/forgot');

        $this->get('https://admin.localhost/login')
            ->assertOk()
            ->assertDontSee('فراموشی رمز عبور؟');
    }

    public function test_customer_can_reset_password_with_email_otp(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create([
            'email' => 'customer@example.com',
            'phone' => null,
            'password' => 'old-password',
        ]);

        $response = $this->post('https://cp.localhost/password/forgot', [
            'login' => 'customer@example.com',
        ]);

        $response->assertRedirect('https://cp.localhost/password/otp?login=customer%40example.com&channel=email');
        Mail::assertSent(CustomerPasswordResetCodeMail::class);

        $record = DB::table('customer_password_reset_tokens')->where('email', 'customer@example.com')->first();
        $this->assertNotNull($record);

        DB::table('customer_password_reset_tokens')->where('email', 'customer@example.com')->update([
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $this->post('https://cp.localhost/password/otp', [
            'login' => 'customer@example.com',
            'channel' => 'email',
            'code' => '123456',
        ])->assertRedirect('https://cp.localhost/password/reset');

        $this->post('https://cp.localhost/password/reset', [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect('https://cp.localhost/login');

        $this->assertTrue(Hash::check('new-password', $customer->refresh()->password));
        $this->assertDatabaseMissing('customer_password_reset_tokens', [
            'email' => 'customer@example.com',
        ]);
    }

    public function test_customer_verification_page_makes_otp_channel_clear(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'sms');

        $this->get('https://cp.localhost/email/verify?phone=09123456789')
            ->assertOk()
            ->assertSee('کد OTP پیامک شده را وارد کنید')
            ->assertSee('موبایل مقصد OTP')
            ->assertSee('کد OTP شش رقمی');
    }
}
