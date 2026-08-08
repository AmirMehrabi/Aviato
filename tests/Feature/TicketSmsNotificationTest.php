<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Sms\KavenegarLookupClient;
use App\Services\Tickets\TicketNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TicketSmsNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_template_sends_only_the_first_part_of_the_customer_name(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'علی رضایی',
            'phone' => '09121111111',
        ]);
        $admin = User::factory()->create(['phone' => '09122222222']);
        $ticket = Ticket::query()->create([
            'number' => 'T2608090001',
            'customer_id' => $customer->id,
            'assigned_user_id' => $admin->id,
            'subject' => 'Network issue',
            'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_NORMAL,
            'source' => 'customer',
            'last_activity_at' => now(),
        ]);

        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar', 'string', 'notifications');
        AppSetting::setValue(AppSetting::TICKET_SMS_NOTIFICATIONS_ENABLED, true, 'boolean', 'ticketing');
        AppSetting::setValue(AppSetting::TICKET_KAVENEGAR_ADMIN_NEW_TEMPLATE, 'admin-new-ticket', 'string', 'ticketing');

        $client = Mockery::mock(KavenegarLookupClient::class);
        $client->shouldReceive('sendLookup')
            ->once()
            ->with('09122222222', 'admin-new-ticket', 'T2608090001', 'علی', 'Support');
        $this->app->instance(KavenegarLookupClient::class, $client);

        app(TicketNotificationService::class)->ticketCreated($ticket);
    }
}
