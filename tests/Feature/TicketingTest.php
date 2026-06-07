<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportTeam;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\VirtualMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Public customer text')
            ->assertDontSee('Private escalation note');
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
