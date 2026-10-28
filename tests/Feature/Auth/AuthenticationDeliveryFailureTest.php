<?php

namespace Tests\Feature\Auth;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Services\Sms\VerificationSmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class AuthenticationDeliveryFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_failure_logs_only_safe_metadata_and_clears_the_code(): void
    {
        $customer = Customer::factory()->create(['phone' => '09120000001']);
        Log::spy();
        $this->mock(VerificationSmsSender::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('private-provider-details', 424));
        });
        $this->post('https://cp.localhost/password/forgot', ['login' => $customer->phone])->assertSessionHasErrors('login');
        $this->assertDatabaseMissing('customer_password_reset_tokens', ['email' => $customer->phone]);
        Log::shouldHaveReceived('warning')->once()->with('Authentication code delivery failed.', [
            'flow' => 'password_reset', 'channel' => 'sms', 'customer_id' => $customer->id,
            'exception_type' => RuntimeException::class, 'error_code' => 424,
        ]);
    }

    public function test_login_failure_logs_only_safe_metadata_and_removes_failed_challenge(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'disabled');
        $customer = Customer::factory()->create(['phone' => '09120000001']);
        Log::spy();
        $this->mock(VerificationSmsSender::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('private-provider-details', 424));
        });
        $this->post('https://cp.localhost/login/otp', ['login' => $customer->phone])->assertSessionHasErrors('login');
        $this->assertDatabaseMissing('customer_login_otp_challenges', ['customer_id' => $customer->id]);
        Log::shouldHaveReceived('warning')->once()->with('Authentication code delivery failed.', [
            'flow' => 'login_otp', 'channel' => 'sms', 'customer_id' => $customer->id,
            'exception_type' => RuntimeException::class, 'error_code' => 424,
        ]);
    }

    public function test_password_reset_uses_aviato_otp_and_stores_only_the_code_hash(): void
    {
        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar', 'string', 'sms');
        AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, 'test-key', 'string', 'kavenegar');
        AppSetting::setValue(AppSetting::KAVENEGAR_TEMPLATE, 'aviato-otp', 'string', 'kavenegar');
        $customer = Customer::factory()->create(['phone' => '09120000001']);
        Http::fake(['api.kavenegar.com/*/verify/lookup.json' => Http::response(['return' => ['status' => 200], 'entries' => [['messageid' => 123]]])]);
        $this->post('https://cp.localhost/password/forgot', ['login' => $customer->phone])
            ->assertRedirect('https://cp.localhost/password/otp?login=09120000001&channel=sms');
        $record = DB::table('customer_password_reset_tokens')->where('email', $customer->phone)->first();
        Http::assertSent(fn ($request) => $request['template'] === 'aviato-otp' && $request['receptor'] === $customer->phone
            && preg_match('/^[0-9]{6}$/', $request['token']) && Hash::check($request['token'], $record->token));
    }
}
