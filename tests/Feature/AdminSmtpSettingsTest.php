<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;

class AdminSmtpSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portals.admin.domain' => 'admin.localhost', 'portals.customer.domain' => 'cp.localhost']);
    }

    public function test_admin_can_save_smtp_and_preserve_an_encrypted_password(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');
        $data = ['smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_encryption' => 'tls', 'smtp_from_address' => 'mail@example.com', 'smtp_password' => 'secret'];
        $this->patch('https://admin.localhost/settings/email', $data)->assertSessionHasNoErrors();
        $stored = AppSetting::getValue(AppSetting::SMTP_PASSWORD);
        $this->assertSame('secret', Crypt::decryptString(substr($stored, 10)));
        $this->patch('https://admin.localhost/settings/email', array_replace($data, ['smtp_password' => '']))->assertSessionHasNoErrors();
        $this->assertSame($stored, AppSetting::getValue(AppSetting::SMTP_PASSWORD));
        $this->get('https://admin.localhost/settings/email')->assertOk()->assertDontSee($stored)->assertSee('settings/email/test');
    }

    public function test_test_email_uses_saved_settings_and_validates_recipient(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->create(), 'admin');
        $this->post('https://admin.localhost/settings/email/test', ['test_email' => 'bad'])->assertSessionHasErrors('test_email');
        $this->post('https://admin.localhost/settings/email/test', ['test_email' => 'test@example.com'])->assertSessionHasErrors('test_email');
        AppSetting::setValue(AppSetting::SMTP_HOST, 'smtp.example.com', 'string', 'smtp');
        // Raw messages are not recorded by MailFake, so assert the actual send call.
        Mail::shouldReceive('raw')->once()->withArgs(fn ($body, $callback) => is_string($body) && is_callable($callback));
        $this->post('https://admin.localhost/settings/email/test', ['test_email' => 'test@example.com'])->assertSessionHas('status');
    }

    public function test_smtp_is_used_globally_and_refreshes_in_long_running_processes(): void
    {
        $manager = app('mail.manager');
        $this->assertInstanceOf(ArrayTransport::class, $manager->mailer()->getSymfonyTransport());
        foreach ([AppSetting::SMTP_HOST => 'smtp.example.com', AppSetting::SMTP_PORT => 587, AppSetting::SMTP_ENCRYPTION => 'tls', AppSetting::SMTP_FROM_ADDRESS => 'sender@example.com', AppSetting::SMTP_PASSWORD => 'secret'] as $key => $value) {
            AppSetting::setValue($key, $value, 'string', 'smtp');
        }
        $first = $manager->mailer();
        $this->assertInstanceOf(EsmtpTransport::class, $first->getSymfonyTransport());
        $this->assertSame('secret', $first->getSymfonyTransport()->getPassword());
        $this->assertSame('smtp', config('mail.mailers.settings_smtp.scheme'));
        $this->assertTrue(config('mail.mailers.settings_smtp.require_tls'));
        AppSetting::setValue(AppSetting::SMTP_HOST, 'changed.example.com', 'string', 'smtp');
        AppSetting::setValue(AppSetting::SMTP_ENCRYPTION, 'ssl', 'string', 'smtp');
        $this->assertNotSame($first, $manager->mailer());
        $this->assertSame('smtps', config('mail.mailers.settings_smtp.scheme'));
        AppSetting::setValue(AppSetting::SMTP_HOST, '', 'string', 'smtp');
        $this->assertInstanceOf(ArrayTransport::class, $manager->mailer()->getSymfonyTransport());
    }

    public function test_transport_failure_does_not_expose_credentials(): void
    {
        AppSetting::setValue(AppSetting::SMTP_HOST, 'smtp.example.com', 'string', 'smtp');
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('password=secret'));
        $response = $this->actingAs(User::factory()->create(), 'admin')->post('https://admin.localhost/settings/email/test', ['test_email' => 'test@example.com']);
        $response->assertSessionHasErrors('test_email');
        $this->assertStringNotContainsString('secret', session('errors')->first('test_email'));
    }

    public function test_guests_cannot_send_test_emails(): void
    {
        Mail::fake();
        $this->post('https://admin.localhost/settings/email/test', ['test_email' => 'test@example.com'])->assertRedirect();
        Mail::assertNothingSent();
    }
}
