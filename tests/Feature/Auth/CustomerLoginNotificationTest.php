<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendNotificationEmail;
use App\Models\AppSetting;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerLoginNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['portals.customer.domain' => 'cp.localhost']);
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, 'email');
        AppSetting::setValue('notifications.templates', ['signed_in' => ['email_enabled' => true]]);
    }

    public function test_explicit_password_login_sends_one_notification_with_trusted_client_ip(): void
    {
        $customer = Customer::factory()->create(['password' => 'password']);
        config(['trustedproxy.proxies' => ['172.19.19.10']]);

        $this->withServerVariables(['REMOTE_ADDR' => '172.19.19.10'])
            ->withHeaders(['X-Forwarded-For' => '192.0.2.99, 198.51.100.42'])
            ->post('https://cp.localhost/login', ['login' => $customer->email, 'password' => 'password'])
            ->assertSessionHasNoErrors()->assertRedirect();

        Queue::assertPushed(SendNotificationEmail::class, fn ($job) => $job->variables['ip'] === '198.51.100.42');
        Queue::assertPushed(SendNotificationEmail::class, 1);
        $this->assertDatabaseHas('notification_deliveries', ['event' => 'signed_in', 'recipient' => $customer->email]);
    }

    public function test_untrusted_forwarded_ip_is_ignored(): void
    {
        $customer = Customer::factory()->create(['password' => 'password']);
        config(['trustedproxy.proxies' => ['172.19.19.10']]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeaders(['X-Forwarded-For' => '192.0.2.99'])
            ->post('https://cp.localhost/login', ['login' => $customer->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();

        Queue::assertPushed(SendNotificationEmail::class, fn ($job) => $job->variables['ip'] === '198.51.100.10');
    }

    public function test_remember_cookie_restores_customer_without_login_notification(): void
    {
        $customer = Customer::factory()->create(['remember_token' => 'saved-token']);
        $guard = Auth::guard('customer');
        $cookie = $customer->id.'|saved-token|'.$customer->getAuthPassword();

        $this->withCookie($guard->getRecallerName(), $cookie)
            ->get('https://cp.localhost/login')->assertRedirect('https://cp.localhost/dashboard');

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertTrue($guard->viaRemember());
        Queue::assertNotPushed(SendNotificationEmail::class);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $customer->id]);
    }

    public function test_rejected_password_logins_do_not_send_success_notifications(): void
    {
        foreach ([['email_verified_at' => null], ['status' => Customer::STATUS_SUSPENDED], ['password' => 'different-password']] as $attributes) {
            $customer = Customer::factory()->create(array_merge(['password' => 'password'], $attributes));
            $this->post('https://cp.localhost/login', ['login' => $customer->email, 'password' => 'password'])
                ->assertSessionHasErrors('login');
            $this->assertGuest('customer');
        }

        Queue::assertNotPushed(SendNotificationEmail::class);
        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    public function test_verified_otp_login_sends_one_success_notification(): void
    {
        $customer = Customer::factory()->create();
        DB::table('customer_login_otp_challenges')->insert([
            'challenge' => 'test-challenge', 'customer_id' => $customer->id,
            'identifier' => $customer->email, 'channel' => 'email',
            'token' => Hash::make('123456'), 'attempts' => 0,
            'sent_at' => now(), 'expires_at' => now()->addMinutes(10),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['customer_login_otp.challenge' => 'test-challenge'])
            ->post('https://cp.localhost/login/otp/verify', ['code' => '123456'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertAuthenticatedAs($customer, 'customer');
        Queue::assertPushed(SendNotificationEmail::class, 1);
    }
}
