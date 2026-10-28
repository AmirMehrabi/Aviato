<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Jobs\SendNotificationEmail;
use App\Mail\BrandedNotificationMail;
use App\Mail\CustomerPasswordResetCodeMail;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\TemplatedNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portals.admin.domain' => 'admin.localhost', 'portals.customer.domain' => 'cp.localhost']);
    }

    public function test_admin_can_edit_preview_and_restore_a_template(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');
        $this->get('https://admin.localhost/settings/notifications')->assertOk()->assertSee('قالب‌ها و اعلان‌ها');
        $data = ['event' => 'password_reset', 'subject' => 'Hello {{name}}', 'body' => 'Safe recovery {{name}}', 'email_enabled' => 0, 'in_app_enabled' => 0];
        $this->patch('https://admin.localhost/settings/notifications/templates', $data)->assertSessionHasNoErrors();
        $this->assertTrue(app(NotificationTemplates::class)->enabled('password_reset', 'email'));
        $this->get('https://admin.localhost/settings/notifications/password_reset/preview')->assertOk()->assertSee('Safe recovery')->assertSee('123456')->assertSee('aviato_logo_full_color.png');
        $this->patch('https://admin.localhost/settings/notifications/templates', $data + ['restore' => 1])->assertSessionHasNoErrors();
        $this->assertSame(app(NotificationTemplates::class)->catalog()['password_reset']['body'], app(NotificationTemplates::class)->all()['password_reset']['body']);
    }

    public function test_templates_escape_html_and_do_not_evaluate_blade(): void
    {
        AppSetting::setValue('notifications.templates', ['password_reset' => ['body' => '<script>alert(1)</script> {{name}} @php(exit)']]);
        $html = (new CustomerPasswordResetCodeMail(Customer::factory()->create(['name' => '<b>Name</b>']), '123456'))->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('@php(exit)', $html);
        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('https://aviato.ir', $html);
    }

    public function test_admin_can_send_a_sample_and_invalid_placeholders_are_rejected(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->create(), 'admin');
        $this->post('https://admin.localhost/settings/notifications/password_reset/test', ['test_email' => 'sample@example.com'])->assertSessionHas('status');
        Mail::assertSent(BrandedNotificationMail::class, fn ($mail) => $mail->code === '123456' && $mail->hasTo('sample@example.com'));
        $this->patch('https://admin.localhost/settings/notifications/templates', ['event' => 'signed_in', 'subject' => '{{unknown}}', 'body' => 'Hello', 'email_enabled' => 1, 'in_app_enabled' => 1])->assertSessionHasErrors('subject');
    }

    public function test_customers_can_only_edit_their_own_optional_preferences(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $preferences = [];
        foreach (app(NotificationTemplates::class)->customerEvents() as $event => $template) {
            $preferences[$event] = ['email_enabled' => 0, 'in_app_enabled' => 1];
        }
        $this->actingAs($customer, 'customer')->get('https://cp.localhost/profile/notifications')->assertOk();
        $this->patch('https://cp.localhost/profile/notifications', ['customer_id' => $other->id, 'preferences' => $preferences])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_notification_preferences', ['customer_id' => $customer->id, 'event' => 'signed_in', 'email_enabled' => false]);
        $this->assertDatabaseMissing('customer_notification_preferences', ['customer_id' => $other->id]);
        $this->assertTrue(app(NotificationTemplates::class)->enabled('password_reset', 'email', $customer));
        $this->get('https://cp.localhost/settings/notifications')->assertNotFound();
    }

    public function test_email_and_in_app_preferences_are_independent_and_rechecked_at_delivery(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        AppSetting::setValue('notifications.templates', ['signed_in' => ['email_enabled' => true, 'in_app_enabled' => false]]);
        $customer->notify(new TemplatedNotification('signed_in', ['ip' => '192.0.2.1', 'time' => 'today'], 'https://cp.localhost/profile'));
        $this->assertSame(0, $customer->notifications()->count());
        Queue::assertPushed(SendNotificationEmail::class);
        $delivery = DB::table('notification_deliveries')->first();
        $this->assertSame('queued', $delivery->status);
        DB::table('customer_notification_preferences')->insert(['customer_id' => $customer->id, 'event' => 'signed_in', 'email_enabled' => false, 'in_app_enabled' => true]);
        Mail::fake();
        (new SendNotificationEmail($delivery->id, $customer->getMorphClass(), $customer->id, ['name' => $customer->name], null))->handle(app(NotificationTemplates::class));
        Mail::assertNothingSent();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'suppressed']);
    }

    public function test_queue_delivery_uses_branded_mail_and_records_success_without_duplicate_send(): void
    {
        Queue::fake();
        Mail::fake();
        $customer = Customer::factory()->create();
        AppSetting::setValue('notifications.templates', ['signed_in' => ['email_enabled' => true]]);
        app(NotificationDispatcher::class)->email($customer, 'signed_in', ['time' => 'today', 'ip' => '192.0.2.1']);
        $delivery = DB::table('notification_deliveries')->first();
        $job = new SendNotificationEmail($delivery->id, $customer->getMorphClass(), $customer->id, ['name' => $customer->name], null);
        $job->handle(app(NotificationTemplates::class));
        $job->handle(app(NotificationTemplates::class));
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'sent', 'attempts' => 1]);
    }

    public function test_disabled_notifications_do_not_queue_email(): void
    {
        Queue::fake();
        app(NotificationDispatcher::class)->email(Customer::factory()->create(), 'signed_in', []);
        Queue::assertNothingPushed();
    }

    public function test_ticket_email_switch_still_controls_template_delivery(): void
    {
        $templates = app(NotificationTemplates::class);
        $customer = Customer::factory()->create();
        $this->assertFalse($templates->enabled('ticket_admin_reply', 'email', $customer));
        AppSetting::setValue(AppSetting::TICKET_EMAIL_NOTIFICATIONS_ENABLED, true);
        $this->assertTrue($templates->enabled('ticket_admin_reply', 'email', $customer));
    }

    public function test_wallet_charge_notification_fires_once_after_successful_transition(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $payment = Payment::create([
            'customer_id' => $customer->id, 'wallet_id' => $customer->wallet->id,
            'provider' => 'dummy', 'type' => 'top_up', 'status' => 'pending', 'amount' => 1000, 'currency' => 'IRR',
        ]);
        DB::transaction(fn () => $payment->update(['status' => 'successful']));
        $payment->update(['description' => 'Updated description']);
        $this->assertSame(1, $customer->notifications()->where('data->event', 'wallet_charged')->count());
    }

    public function test_failed_email_is_marked_failed_after_queue_retries(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        AppSetting::setValue('notifications.templates', ['signed_in' => ['email_enabled' => true]]);
        app(NotificationDispatcher::class)->email($customer, 'signed_in', []);
        $delivery = DB::table('notification_deliveries')->first();
        $job = new SendNotificationEmail($delivery->id, $customer->getMorphClass(), $customer->id, [], null);
        Mail::shouldReceive('mailer')->with('settings_smtp')->andReturnSelf();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('private transport details'));
        try {
            $job->handle(app(NotificationTemplates::class));
            $this->fail('Expected transport failure to trigger queue retry.');
        } catch (\RuntimeException $exception) {
            $job->failed($exception);
        }
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'failed', 'attempts' => 1]);
    }

    public function test_staff_without_settings_permission_cannot_manage_templates(): void
    {
        $staff = User::factory()->create(['role' => AdminRole::Custom, 'permissions' => ['tickets.read']]);
        $this->actingAs($staff, 'admin')->get('https://admin.localhost/settings/notifications')->assertForbidden();
        $this->post('https://admin.localhost/settings/notifications/password_reset/test', ['test_email' => 'someone@example.com'])->assertForbidden();
    }

    public function test_guest_cannot_edit_notification_templates(): void
    {
        $this->patch('https://admin.localhost/settings/notifications/templates', [])->assertRedirect();
    }
}
