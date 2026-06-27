<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portals.admin.domain' => 'admin.localhost',
            'portals.customer.domain' => 'cp.localhost',
        ]);
    }

    public function test_admin_can_save_ticket_notification_settings(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->patch('https://admin.localhost/settings', [
                'currency' => 'IRR',
                'customer_verification_mode' => 'email',
                'national_code_verification_enabled' => 0,
                'national_code_verification_token' => '',
                'sms_gateway' => 'kavenegar',
                'sms0098_username' => '',
                'sms0098_password' => '',
                'sms0098_panel_no' => '',
                'kavenegar_api_key' => 'secret',
                'kavenegar_template' => 'verify',
                'ticket_email_notifications_enabled' => 1,
                'ticket_sms_notifications_enabled' => 1,
                'smtp_host' => 'smtp.example.test',
                'smtp_port' => 587,
                'smtp_username' => 'mailer',
                'smtp_password' => 'smtp-secret',
                'smtp_encryption' => 'tls',
                'smtp_from_address' => 'support@example.test',
                'smtp_from_name' => 'Aviato Support',
                'ticket_kavenegar_customer_created_template' => 'ticket_created',
                'ticket_kavenegar_admin_new_template' => 'admin_new_ticket',
                'ticket_kavenegar_customer_reply_template' => 'customer_reply',
                'ticket_kavenegar_admin_reply_template' => 'admin_reply',
                'ticket_kavenegar_assignment_template' => 'ticket_assigned',
                'vm_creation_charge_enabled' => 0,
                'vm_creation_charge_percentage' => 0,
                'unverified_customer_vm_limit' => 2,
                'verified_customer_vm_limit' => 0,
                'deleted_vm_cooldown_days' => 30,
                'vm_rebuild_fee_multiplier_percentage' => 50,
                'tax_enabled' => 0,
                'tax_rate_percentage' => 0,
            ])
            ->assertRedirect();

        $this->assertTrue(AppSetting::ticketEmailNotificationsEnabled());
        $this->assertTrue(AppSetting::ticketSmsNotificationsEnabled());
        $this->assertSame('smtp.example.test', AppSetting::getValue(AppSetting::SMTP_HOST));
        $this->assertSame('ticket_created', AppSetting::getValue(AppSetting::TICKET_KAVENEGAR_CUSTOMER_CREATED_TEMPLATE));
    }
}
