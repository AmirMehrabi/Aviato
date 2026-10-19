<?php

namespace Tests\Feature;

use App\Mail\CustomerPasswordResetCodeMail;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerEmailPasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portals.admin.domain' => 'admin.localhost', 'portals.customer.domain' => 'cp.localhost']);
    }

    public function test_real_generated_code_completes_email_recovery_and_cannot_be_reused(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['password' => 'original-password']);
        $this->post('https://cp.localhost/password/forgot', ['login' => $customer->email])->assertRedirect();
        $code = null;
        Mail::assertSent(CustomerPasswordResetCodeMail::class, function ($mail) use (&$code, $customer): bool {
            $code = $mail->code;

            return $mail->hasTo($customer->email) && str_contains($mail->render(), $code);
        });
        $this->post('https://cp.localhost/password/otp', ['login' => $customer->email, 'channel' => 'email', 'code' => $code])->assertRedirect('https://cp.localhost/password/reset');
        $this->post('https://cp.localhost/password/reset', ['password' => 'replacement-password', 'password_confirmation' => 'replacement-password'])->assertRedirect('https://cp.localhost/login');
        $this->assertTrue(Hash::check('replacement-password', $customer->refresh()->password));
        $this->post('https://cp.localhost/password/otp', ['login' => $customer->email, 'channel' => 'email', 'code' => $code])->assertSessionHasErrors('code');
    }

    public function test_expired_verified_reset_session_cannot_change_password(): void
    {
        $customer = $this->verifiedReset();
        $this->travel(11)->minutes();
        $this->post('https://cp.localhost/password/reset', ['password' => 'replacement-password', 'password_confirmation' => 'replacement-password'])->assertSessionHasErrors('login');
        $this->assertTrue(Hash::check('original-password', $customer->refresh()->password));
    }

    public function test_requesting_a_new_code_invalidates_an_already_verified_session(): void
    {
        $customer = $this->verifiedReset();
        DB::table('customer_password_reset_tokens')->where('email', $customer->email)->update(['token' => Hash::make('654321')]);
        $this->post('https://cp.localhost/password/reset', ['password' => 'replacement-password', 'password_confirmation' => 'replacement-password'])->assertSessionHasErrors('login');
        $this->assertTrue(Hash::check('original-password', $customer->refresh()->password));
    }

    private function verifiedReset(): Customer
    {
        $customer = Customer::factory()->create(['password' => 'original-password']);
        DB::table('customer_password_reset_tokens')->insert(['email' => $customer->email, 'token' => Hash::make('123456'), 'created_at' => now()]);
        $this->post('https://cp.localhost/password/otp', ['login' => $customer->email, 'channel' => 'email', 'code' => '123456'])->assertRedirect('https://cp.localhost/password/reset');

        return $customer;
    }
}
