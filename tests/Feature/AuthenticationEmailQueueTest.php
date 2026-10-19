<?php

namespace Tests\Feature;

use App\Jobs\SendAuthenticationEmail;
use App\Models\AppSetting;
use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuthenticationEmailQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_request_queues_email_without_calling_the_smtp_transport(): void
    {
        Queue::fake();
        Mail::shouldReceive('to')->never();
        Mail::shouldReceive('mailer')->never();
        $customer = Customer::factory()->create();
        AppSetting::setValue(AppSetting::SMTP_HOST, 'unreachable.invalid', 'string', 'smtp');
        $this->post('https://cp.localhost/password/forgot', ['login' => $customer->email])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', fn ($status) => str_contains($status, 'صف'));
        Queue::assertPushedOn('auth-mail', SendAuthenticationEmail::class);
        $this->assertDatabaseHas('notification_deliveries', ['event' => 'password_reset', 'status' => 'queued', 'attempts' => 0]);
    }

    public function test_a_sync_mail_queue_is_rejected_without_attempting_smtp(): void
    {
        config(['mail.queue_connection' => 'sync']);
        Mail::shouldReceive('to')->never();
        $customer = Customer::factory()->create();
        $this->post('https://cp.localhost/password/forgot', ['login' => $customer->email])->assertSessionHasErrors('login');
        $this->assertDatabaseMissing('customer_password_reset_tokens', ['email' => $customer->email]);
    }

    public function test_expired_or_replaced_codes_are_not_sent_by_workers(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        DB::table('customer_password_reset_tokens')->insert(['email' => $customer->email, 'token' => Hash::make('654321'), 'created_at' => now()]);
        foreach ([['123456', now()->addMinutes(10)->timestamp], ['654321', now()->subMinute()->timestamp]] as [$code, $expires]) {
            $job = $this->job($customer, $code, $expires);
            $job->handle();
            $this->assertDatabaseHas('notification_deliveries', ['id' => $job->deliveryId, 'status' => 'suppressed']);
        }
        Mail::assertNothingSent();
    }

    public function test_missing_smtp_does_not_silently_send_security_codes_to_the_log_mailer(): void
    {
        config(['mail.default' => 'log']);
        $customer = Customer::factory()->create();
        DB::table('customer_password_reset_tokens')->insert(['email' => $customer->email, 'token' => Hash::make('123456'), 'created_at' => now()]);
        $job = $this->job($customer, '123456', now()->addMinutes(10)->timestamp);
        try {
            $job->handle();
            $this->fail('Expected unconfigured SMTP to fail in the worker.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('SMTP is not configured', $exception->getMessage());
            $job->failed($exception);
        }
        $this->assertDatabaseHas('notification_deliveries', ['id' => $job->deliveryId, 'status' => 'failed', 'attempts' => 1]);
    }

    public function test_security_codes_are_encrypted_in_queue_payloads(): void
    {
        $customer = Customer::factory()->create();
        $job = $this->job($customer, '123456', now()->addMinutes(10)->timestamp);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        $id = app('queue')->connection('database')->push($job, '', 'auth-mail');
        $payload = DB::table('jobs')->where('id', $id)->value('payload');
        $this->assertStringNotContainsString('123456', $payload);
        $this->assertStringNotContainsString('code";', $payload);
    }

    private function job(Customer $customer, string $code, int $expires): SendAuthenticationEmail
    {
        $id = DB::table('notification_deliveries')->insertGetId(['event' => 'password_reset', 'recipient' => $customer->email, 'status' => 'queued', 'created_at' => now(), 'updated_at' => now()]);

        return new SendAuthenticationEmail($id, $customer->id, 'password_reset', $code, $expires);
    }
}
