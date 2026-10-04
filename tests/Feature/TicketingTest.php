<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportTeam;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Services\Tickets\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TicketingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portals.admin.domain' => 'admin.localhost',
            'portals.customer.domain' => 'cp.localhost',
        ]);

        Customer::all();
        Customer::created(fn (Customer $c) => \DB::table('wallets')->where('customer_id', $c->id)->update(['balance' => 10_000_000]));
    }

    public function test_customer_can_create_ticket_with_own_virtual_machine(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vmFor($customer);
        $category = TicketCategory::query()->firstOrFail();

        $this->actingAs($customer, 'customer')
            ->post('https://cp.localhost/tickets', [
                'ticket_category_id' => $category->id,
                'virtual_machine_id' => $vm->id,
                'subject' => 'Network issue',
                'priority' => Ticket::PRIORITY_HIGH,
                'body' => 'Packet loss on this VM.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'customer_id' => $customer->id,
            'virtual_machine_id' => $vm->id,
            'subject' => 'Network issue',
            'priority' => Ticket::PRIORITY_HIGH,
        ]);
        $this->assertDatabaseHas('ticket_messages', [
            'author_type' => Customer::class,
            'author_id' => $customer->id,
            'type' => TicketMessage::TYPE_REPLY,
        ]);
    }

    public function test_customer_cannot_link_another_customers_virtual_machine(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $foreignVm = $this->vmFor($other);
        $category = TicketCategory::query()->firstOrFail();

        $this->actingAs($customer, 'customer')
            ->post('https://cp.localhost/tickets', [
                'ticket_category_id' => $category->id,
                'virtual_machine_id' => $foreignVm->id,
                'subject' => 'Wrong VM',
                'priority' => Ticket::PRIORITY_NORMAL,
                'body' => 'This should fail.',
            ])
            ->assertNotFound();
    }

    public function test_round_robin_assignment_rotates_active_team_agents(): void
    {
        $customer = Customer::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $team = SupportTeam::query()->create(['name' => 'NOC', 'slug' => 'noc', 'is_active' => true]);
        $team->users()->syncWithPivotValues([$first->id, $second->id], ['is_active' => true]);
        $category = TicketCategory::query()->create([
            'name' => 'Network',
            'slug' => 'network',
            'support_team_id' => $team->id,
            'assignment_strategy' => TicketCategory::ASSIGNMENT_ROUND_ROBIN,
            'is_active' => true,
        ]);

        $this->actingAs($customer, 'customer')->post('https://cp.localhost/tickets', [
            'ticket_category_id' => $category->id,
            'subject' => 'First',
            'priority' => Ticket::PRIORITY_NORMAL,
            'body' => 'First issue',
        ]);
        $this->actingAs($customer, 'customer')->post('https://cp.localhost/tickets', [
            'ticket_category_id' => $category->id,
            'subject' => 'Second',
            'priority' => Ticket::PRIORITY_NORMAL,
            'body' => 'Second issue',
        ]);

        $this->assertSame([$first->id, $second->id], Ticket::query()->orderBy('id')->pluck('assigned_user_id')->all());
    }

    public function test_admin_internal_notes_are_hidden_from_customer_thread(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $category = TicketCategory::query()->firstOrFail();

        $this->actingAs($customer, 'customer')->post('https://cp.localhost/tickets', [
            'ticket_category_id' => $category->id,
            'subject' => 'Need help',
            'priority' => Ticket::PRIORITY_NORMAL,
            'body' => 'Public customer text',
        ]);
        $ticket = Ticket::query()->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post('https://admin.localhost/tickets/'.$ticket->number.'/reply', [
                'body' => 'Private escalation note',
                'internal' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($customer, 'customer')
            ->get('https://cp.localhost/tickets/'.$ticket->number)
            ->assertOk()
            ->assertSee('درخواست اولیه')
            ->assertSee('گفتگو')
            ->assertSee('وضعیت تیکت')
            ->assertSee('Public customer text')
            ->assertDontSee('Private escalation note');

        $this->assertSame(0, $customer->fresh()->notifications()->count());
    }

    public function test_admin_reply_is_unread_for_customer_until_ticket_is_seen(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $category = TicketCategory::query()->firstOrFail();

        $this->actingAs($customer, 'customer')->post('https://cp.localhost/tickets', [
            'ticket_category_id' => $category->id,
            'subject' => 'Reply visibility',
            'priority' => Ticket::PRIORITY_NORMAL,
            'body' => 'Initial customer request',
        ]);
        $ticket = Ticket::query()->firstOrFail();

        $this->actingAs($admin, 'admin')->post('https://admin.localhost/tickets/'.$ticket->number.'/reply', [
            'body' => 'A public support response',
            'mode' => 'public',
        ])->assertRedirect();

        $adminMessage = $ticket->messages()->where('author_type', User::class)->latest()->firstOrFail();
        $this->assertNull($adminMessage->seen_by_customer_at);
        $this->assertSame(1, $customer->fresh()->unreadNotifications()->count());

        $this->actingAs($customer, 'customer')
            ->get('https://cp.localhost/tickets?attention=unread')
            ->assertOk()
            ->assertSee('پاسخ جدید')
            ->assertSee('A public support response');

        $this->actingAs($customer, 'customer')
            ->postJson('https://cp.localhost/tickets/'.$ticket->number.'/seen')
            ->assertOk()
            ->assertJson([
                'unread_replies_count' => 0,
                'notification_unread_count' => 0,
            ]);

        $this->assertNotNull($adminMessage->fresh()->seen_by_customer_at);
        $this->assertSame(0, $customer->fresh()->unreadNotifications()->count());
    }

    public function test_admin_created_ticket_notifies_customer_in_app(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $category = TicketCategory::query()->firstOrFail();

        $this->actingAs($admin, 'admin')->post('https://admin.localhost/tickets', [
            'customer_id' => $customer->id,
            'ticket_category_id' => $category->id,
            'subject' => 'Proactive support',
            'priority' => Ticket::PRIORITY_NORMAL,
            'body' => 'We opened this ticket for you.',
        ])->assertRedirect();

        $notification = $customer->fresh()->notifications()->firstOrFail();

        $this->assertSame('ticket_created_for_customer', $notification->data['event']);
        $this->assertSame('تیکت جدید برای شما ثبت شد', $notification->data['title']);
        $this->assertStringContainsString('/tickets/', $notification->data['url']);
    }

    public function test_customer_reply_is_unread_for_admin_until_ticket_is_seen(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $category = TicketCategory::query()->firstOrFail();

        $this->actingAs($customer, 'customer')->post('https://cp.localhost/tickets', [
            'ticket_category_id' => $category->id,
            'subject' => 'Admin unread queue',
            'priority' => Ticket::PRIORITY_HIGH,
            'body' => 'Customer needs a response.',
        ]);
        $ticket = Ticket::query()->firstOrFail();
        $customerMessage = $ticket->messages()->where('author_type', Customer::class)->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->get('https://admin.localhost/tickets?attention=unread')
            ->assertOk()
            ->assertSee('Admin unread queue')
            ->assertSee('پاسخ جدید');

        $this->actingAs($admin, 'admin')
            ->get('https://admin.localhost/tickets/'.$ticket->number)
            ->assertOk()
            ->assertSee('گفتگوی تیکت')
            ->assertSee('درخواست اولیه مشتری');

        $this->actingAs($admin, 'admin')
            ->postJson('https://admin.localhost/tickets/'.$ticket->number.'/seen')
            ->assertOk()
            ->assertJson(['unread_replies_count' => 0]);

        $this->assertNotNull($customerMessage->fresh()->seen_by_admin_at);
    }

    public function test_customer_cannot_reopen_or_reply_to_an_admin_closed_ticket(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $ticket = Ticket::create([
            'customer_id' => $customer->id, 'number' => 'T-CLOSED-1',
            'subject' => 'Closed support request', 'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_NORMAL,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch('https://admin.localhost/tickets/'.$ticket->number.'/status', ['status' => Ticket::STATUS_CLOSED])
            ->assertRedirect();
        $closedAt = $ticket->refresh()->closed_at;
        $messageCount = $ticket->messages()->count();
        $eventCount = $ticket->events()->count();

        $this->actingAs($customer, 'customer')
            ->get('https://cp.localhost/tickets/'.$ticket->number)
            ->assertOk()->assertDontSee('باز کردن دوباره تیکت')
            ->assertDontSee('customer-ticket-reply');
        $this->patch('https://cp.localhost/tickets/'.$ticket->number.'/reopen')->assertNotFound();
        $this->post('https://cp.localhost/tickets/'.$ticket->number.'/reply', ['body' => 'Please reopen this ticket.'])
            ->assertForbidden();

        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->refresh()->status);
        $this->assertTrue($closedAt->equalTo($ticket->closed_at));
        $this->assertSame($messageCount, $ticket->messages()->count());
        $this->assertSame($eventCount, $ticket->events()->count());

        $this->actingAs($admin, 'admin')
            ->patch('https://admin.localhost/tickets/'.$ticket->number.'/status', ['status' => Ticket::STATUS_OPEN])
            ->assertRedirect();
        $this->assertNull($ticket->refresh()->closed_at);
        $this->actingAs($customer, 'customer')
            ->post('https://cp.localhost/tickets/'.$ticket->number.'/reply', ['body' => 'Reply after support reopened the ticket.'])
            ->assertRedirect();
        $this->assertSame($messageCount + 1, $ticket->messages()->count());
    }

    public function test_ticket_service_rejects_customer_reopening_a_closed_ticket(): void
    {
        $customer = Customer::factory()->create();
        $ticket = Ticket::create([
            'customer_id' => $customer->id, 'number' => 'T-CLOSED-2',
            'subject' => 'Closed support request', 'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_NORMAL,
        ]);
        $staleTicket = $ticket->fresh();
        $ticket->update(['status' => Ticket::STATUS_CLOSED, 'closed_at' => now()]);
        $service = app(TicketService::class);

        foreach (['status', 'reply'] as $operation) {
            try {
                if ($operation === 'status') {
                    $service->updateStatus($ticket, $customer, Ticket::STATUS_OPEN);
                } else {
                    $service->reply($staleTicket, $customer, 'Please reopen this ticket.');
                }
                $this->fail('Customers must not reopen closed tickets through '.$operation.'.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->refresh()->status);
        $this->assertSame(0, $ticket->messages()->count());
        $this->assertSame(0, $ticket->events()->count());
    }

    private function vmFor(Customer $customer): VirtualMachine
    {
        return VirtualMachine::query()->create([
            'customer_id' => $customer->id,
            'name' => 'vm-'.$customer->id,
            'hostname' => 'vm-'.$customer->id,
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 50,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);
    }
}
