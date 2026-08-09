<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\Sms\KavenegarLookupClient;
use App\Services\Tickets\TicketNotificationService;
use App\Services\Tickets\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class TicketSmsNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_client_sends_spaced_values_as_token10_and_token20(): void
    {
        AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, 'secret', 'string', 'kavenegar');
        Http::fake([
            'api.kavenegar.com/*' => Http::response([
                'return' => ['status' => 200, 'message' => 'تایید شد'],
                'entries' => [],
            ]),
        ]);

        app(KavenegarLookupClient::class)->sendLookup(
            '09121111111',
            'aviatoticketcreated',
            'T2608090001',
            token10: 'زیرساخت ابری',
            token20: 'علی محمد رضایی',
        );

        Http::assertSent(fn ($request): bool => $request['token'] === 'T2608090001'
            && $request['token10'] === 'زیرساخت ابری'
            && $request['token20'] === 'علی محمد رضایی'
            && $request['template'] === 'aviatoticketcreated'
            && ! isset($request['token2'], $request['token3']));
    }

    public function test_ticket_creation_sends_customer_and_admin_templates_with_full_name_and_category(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'علی رضایی',
            'phone' => '09121111111',
        ]);
        $admin = User::factory()->create(['phone' => '09122222222']);
        $category = TicketCategory::query()->create([
            'name' => 'زیرساخت ابری',
            'slug' => 'cloud-infrastructure',
            'is_active' => true,
        ]);
        $ticket = Ticket::query()->create([
            'number' => 'T2608090001',
            'customer_id' => $customer->id,
            'assigned_user_id' => $admin->id,
            'ticket_category_id' => $category->id,
            'subject' => 'Network issue',
            'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_NORMAL,
            'source' => 'customer',
            'last_activity_at' => now(),
        ]);

        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar', 'string', 'notifications');
        AppSetting::setValue(AppSetting::TICKET_SMS_NOTIFICATIONS_ENABLED, true, 'boolean', 'ticketing');
        AppSetting::setValue(AppSetting::TICKET_KAVENEGAR_CUSTOMER_CREATED_TEMPLATE, 'aviatoticketcreated', 'string', 'ticketing');
        AppSetting::setValue(AppSetting::TICKET_KAVENEGAR_ADMIN_NEW_TEMPLATE, 'aviatonewticket', 'string', 'ticketing');

        $client = Mockery::mock(KavenegarLookupClient::class);
        $client->shouldReceive('sendLookup')
            ->once()
            ->with('09121111111', 'aviatoticketcreated', 'T2608090001', null, null, 'زیرساخت ابری', 'علی رضایی');
        $client->shouldReceive('sendLookup')
            ->once()
            ->with('09122222222', 'aviatonewticket', 'T2608090001', null, null, 'زیرساخت ابری', 'علی رضایی');
        $this->app->instance(KavenegarLookupClient::class, $client);

        app(TicketNotificationService::class)->ticketCreated($ticket);
    }

    public function test_ticket_replies_use_full_customer_name_and_translated_status(): void
    {
        $customer = Customer::factory()->create(['name' => 'علی رضایی', 'phone' => '09121111111']);
        $admin = User::factory()->create(['phone' => '09122222222']);
        $ticket = Ticket::query()->create([
            'number' => 'T2608090002',
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
        AppSetting::setValue(AppSetting::TICKET_KAVENEGAR_ADMIN_REPLY_TEMPLATE, 'aviatocustomerreply', 'string', 'ticketing');
        AppSetting::setValue(AppSetting::TICKET_KAVENEGAR_CUSTOMER_REPLY_TEMPLATE, 'aviatosupportreply', 'string', 'ticketing');

        $client = Mockery::mock(KavenegarLookupClient::class);
        $client->shouldReceive('sendLookup')
            ->once()
            ->with('09122222222', 'aviatocustomerreply', 'T2608090002', null, null, 'باز', 'علی رضایی');
        $client->shouldReceive('sendLookup')
            ->once()
            ->with('09121111111', 'aviatosupportreply', 'T2608090002', null, null, 'پاسخ داده شده', 'علی رضایی');
        $this->app->instance(KavenegarLookupClient::class, $client);

        app(TicketNotificationService::class)->customerReply($ticket);
        $ticket->forceFill(['status' => Ticket::STATUS_ANSWERED])->save();
        app(TicketNotificationService::class)->adminReply($ticket->refresh());
    }

    public function test_reassignment_sends_assignment_template_to_new_assignee(): void
    {
        $customer = Customer::factory()->create();
        $actor = User::factory()->create();
        $previousAssignee = User::factory()->create();
        $newAssignee = User::factory()->create(['name' => 'سارا احمدی', 'phone' => '09123333333']);
        $category = TicketCategory::query()->create([
            'name' => 'زیرساخت ابری',
            'slug' => 'cloud-infrastructure',
            'is_active' => true,
        ]);
        $ticket = Ticket::query()->create([
            'number' => 'T2608090003',
            'customer_id' => $customer->id,
            'assigned_user_id' => $previousAssignee->id,
            'ticket_category_id' => $category->id,
            'subject' => 'Network issue',
            'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_NORMAL,
            'source' => 'customer',
            'last_activity_at' => now(),
        ]);

        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar', 'string', 'notifications');
        AppSetting::setValue(AppSetting::TICKET_SMS_NOTIFICATIONS_ENABLED, true, 'boolean', 'ticketing');
        AppSetting::setValue(AppSetting::TICKET_KAVENEGAR_ASSIGNMENT_TEMPLATE, 'aviatoticketassigned', 'string', 'ticketing');

        $client = Mockery::mock(KavenegarLookupClient::class);
        $client->shouldReceive('sendLookup')
            ->once()
            ->with('09123333333', 'aviatoticketassigned', 'T2608090003', null, null, 'زیرساخت ابری', 'سارا احمدی');
        $this->app->instance(KavenegarLookupClient::class, $client);

        app(TicketService::class)->updateAssignment($ticket, $actor, [
            'assigned_user_id' => $newAssignee->id,
        ]);
    }
}
