<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\UsageSettlement;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portals.admin.domain' => 'admin.localhost', 'portals.customer.domain' => 'cp.localhost']);
        $this->actingAs(User::factory()->create(), 'admin');
        $this->customer = Customer::factory()->create();
    }

    public function test_queue_counts_all_failures_and_paginates_visible_items(): void
    {
        foreach (range(1, 10) as $number) {
            $this->failedVm('failed-vm-'.$number);
        }

        $this->get('https://admin.localhost/dashboard')
            ->assertOk()
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['total_count'] === 10
                && $dashboard['items']->count() === 8
                && $dashboard['remaining_count'] === 2
                && $dashboard['health'][1]['count'] === 10);

        $this->get('https://admin.localhost/dashboard?page=2')
            ->assertOk()
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['items']->count() === 2
                && $dashboard['remaining_count'] === 0);
    }

    public function test_queue_prioritizes_failed_machine_and_only_open_tickets_need_response(): void
    {
        $this->failedVm('critical-machine');
        Ticket::create([
            'number' => 'T-OPEN-1',
            'customer_id' => $this->customer->id,
            'subject' => 'Urgent response needed',
            'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_URGENT,
        ]);
        Ticket::create([
            'number' => 'T-PENDING-1',
            'customer_id' => $this->customer->id,
            'subject' => 'Waiting for customer',
            'status' => Ticket::STATUS_PENDING,
            'priority' => Ticket::PRIORITY_URGENT,
        ]);

        $this->get('https://admin.localhost/dashboard')
            ->assertOk()
            ->assertSeeInOrder(['critical-machine', 'Urgent response needed'])
            ->assertViewHas('activity', fn ($activity) => $activity['awaiting_tickets']->pluck('number')->all() === ['T-OPEN-1'])
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['health'][3]['count'] === 1);

        $this->get('https://admin.localhost/dashboard?category=tickets')
            ->assertOk()
            ->assertSee('Urgent response needed')
            ->assertViewHas('dashboard', fn ($dashboard) => $dashboard['items']->every(fn ($item) => $item['type'] === 'ticket'))
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['total_count'] === 1
                && $dashboard['items']->count() === 1);
    }

    public function test_dismissed_item_on_a_later_page_is_counted_and_reappears_after_change(): void
    {
        $machines = collect(range(1, 10))->map(fn (int $number): VirtualMachine => $this->failedVm('failed-vm-'.$number));
        $secondPage = $this->get('https://admin.localhost/dashboard?page=2')->assertOk();
        $item = $secondPage->viewData('dashboard')['items']->first();

        $this->post('https://admin.localhost/dashboard/warnings/dismiss', ['warning_key' => $item['key']])
            ->assertRedirect();

        $this->get('https://admin.localhost/dashboard')
            ->assertOk()
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['total_count'] === 10
                && $dashboard['hidden_count'] === 1
                && $dashboard['remaining_count'] === 1);

        $this->travel(2)->seconds();
        $machine = $machines->firstWhere('name', $item['title']);
        $machine->touch();
        $this->get('https://admin.localhost/dashboard')->assertViewHas('dashboard', fn ($dashboard) => $dashboard['hidden_count'] === 1);
        $machine->update(['remote_state' => ['provisioning_error' => 'A new failure']]);

        $this->get('https://admin.localhost/dashboard')
            ->assertOk()
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['hidden_count'] === 0);
    }

    public function test_recovered_backup_does_not_remain_in_action_queue(): void
    {
        $vm = $this->failedVm('backup-machine');
        $vm->update(['provisioning_status' => VirtualMachine::PROVISION_READY]);
        $vm->backups()->create(['status' => VmBackup::STATUS_FAILED, 'source' => VmBackup::SOURCE_MANUAL]);
        $vm->backups()->create(['status' => VmBackup::STATUS_READY, 'source' => VmBackup::SOURCE_MANUAL]);

        $this->get('https://admin.localhost/dashboard')
            ->assertOk()
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['total_count'] === 0);

        $vm->backups()->create(['status' => VmBackup::STATUS_FAILED, 'source' => VmBackup::SOURCE_MANUAL, 'error' => 'Backup storage unavailable']);

        $this->get('https://admin.localhost/dashboard')
            ->assertOk()
            ->assertSee('بکاپ ناموفق')
            ->assertSee(route('admin.virtual-machines.show', $vm).'#backup-status')
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['total_count'] === 1);

        $this->get(route('admin.virtual-machines.show', $vm))
            ->assertOk()
            ->assertSee('Backup storage unavailable');
    }

    public function test_finance_snapshot_separates_paid_date_from_attempt_date_and_currencies(): void
    {
        $paidToday = $this->payment(Payment::STATUS_SUCCESSFUL, 2_000_000, 'IRR', 'mellat');
        $paidToday->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();
        app(WalletService::class)->credit($this->customer, $paidToday->amount, 'Gateway top up', reference: $paidToday);
        $this->payment(Payment::STATUS_SUCCESSFUL, 50, 'USD', 'zibal');
        $this->payment(Payment::STATUS_FAILED, 900_000, 'IRR', 'mellat');
        $this->payment(Payment::STATUS_PENDING, 900_000, 'IRR', 'mellat');

        $this->get('https://admin.localhost/dashboard?period=1')
            ->assertOk()
            ->assertDontSee('آخرین پرداخت‌های درگاه')
            ->assertViewHas('finance', fn (array $finance): bool => $finance['collected']->count() === 2
                && $finance['collected']->firstWhere('currency', 'IRR')['amount'] === 2_000_000
                && $finance['collected']->firstWhere('currency', 'USD')['amount'] === 50
                && $finance['successful_attempts'] === 1
                && $finance['failed_attempts'] === 1
                && $finance['pending_attempts'] === 1
                && $finance['success_rate'] === 50);

        $this->get('https://admin.localhost/billing/payments?date_basis=paid&status=successful&from='.today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()->assertViewHas('payments', fn ($payments): bool => $payments->getCollection()->contains('id', $paidToday->id));
    }

    public function test_payment_exceptions_enter_queue_and_resolve_after_credit_or_status_change(): void
    {
        $uncredited = $this->payment(Payment::STATUS_SUCCESSFUL, 1_000_000, 'IRR', 'mellat');
        $uncredited->update(['paid_at' => now()->subMinutes(10)]);
        $stale = $this->payment(Payment::STATUS_PENDING, 1_000_000, 'IRR', 'zibal');
        $stale->forceFill(['created_at' => now()->subMinutes(20), 'updated_at' => now()->subMinutes(20)])->save();

        $this->get('https://admin.localhost/dashboard?category=payments')
            ->assertOk()
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['total_count'] === 2
                && $dashboard['items']->every(fn (array $item): bool => str_contains($item['url'], '/billing/payments/')))
            ->assertViewHas('finance', fn (array $finance): bool => $finance['aged_pending'] === 1 && $finance['reconciliation_count'] === 1);

        app(WalletService::class)->credit($this->customer, $uncredited->amount, 'Gateway top up', reference: $uncredited);
        $stale->update(['status' => Payment::STATUS_FAILED, 'failed_at' => now()]);

        $this->get('https://admin.localhost/dashboard?category=payments')
            ->assertOk()
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['total_count'] === 0);
    }

    public function test_gateway_rate_needs_ten_finalized_attempts_and_consumption_requires_settlement(): void
    {
        foreach (range(1, 8) as $number) {
            $this->payment(Payment::STATUS_SUCCESSFUL, 100_000, 'IRR', 'mellat');
        }
        $this->payment(Payment::STATUS_FAILED, 100_000, 'IRR', 'mellat');
        $this->payment(Payment::STATUS_PENDING, 100_000, 'IRR', 'mellat');
        UsageSettlement::create([
            'customer_id' => $this->customer->id,
            'scope_key' => 'customer:'.$this->customer->id,
            'service_date' => today(),
            'amount' => 400_000,
        ]);

        $firstSnapshot = $this->get('https://admin.localhost/dashboard')->assertOk();
        $firstSnapshot->assertViewHas('finance', fn (array $finance): bool => $finance['providers']->firstWhere('key', 'mellat')['rate'] === null);
        $this->assertSame(0, $firstSnapshot->viewData('finance')['settled_consumption']);

        $this->payment(Payment::STATUS_CANCELLED, 100_000, 'IRR', 'mellat');
        UsageSettlement::query()->update(['settled_at' => now()]);

        $secondSnapshot = $this->get('https://admin.localhost/dashboard')->assertOk();
        $secondSnapshot->assertViewHas('finance', fn (array $finance): bool => $finance['providers']->firstWhere('key', 'mellat')['rate'] === 80);
        $this->assertSame(400_000, $secondSnapshot->viewData('finance')['settled_consumption']);
    }

    public function test_period_filters_activity_but_keeps_older_outstanding_tickets(): void
    {
        $old = Customer::factory()->create(['created_at' => now()->subDays(10)]);
        $oldVm = $this->failedVm('older-machine');
        $oldVm->forceFill(['created_at' => now()->subDays(10)])->save();
        $newVm = $this->failedVm('new-machine');
        Ticket::create(['number' => 'OLDER-TICKET', 'customer_id' => $old->id, 'subject' => 'Still awaiting reply', 'status' => Ticket::STATUS_OPEN, 'priority' => Ticket::PRIORITY_NORMAL, 'created_at' => now()->subDays(10), 'last_activity_at' => now()->subDays(10)]);
        Ticket::create(['number' => 'RECENT-TICKET', 'customer_id' => $old->id, 'subject' => 'Recent activity', 'status' => Ticket::STATUS_CLOSED, 'priority' => Ticket::PRIORITY_NORMAL, 'created_at' => now()->subDays(10), 'last_activity_at' => now()]);

        Ticket::whereIn('number', ['OLDER-TICKET', 'RECENT-TICKET'])->get()->each(fn ($ticket) => $ticket->forceFill(['created_at' => now()->subDays(10)])->save());
        $response = $this->get('https://admin.localhost/dashboard?period=7')->assertOk();
        $activity = $response->viewData('activity');
        $this->assertSame(1, $activity['machines_count']);
        $this->assertSame([$newVm->id], $activity['machines']->modelKeys());
        $this->assertSame([$this->customer->id], $activity['customers']->modelKeys());
        $this->assertSame(['OLDER-TICKET'], $activity['awaiting_tickets']->pluck('number')->all());
        $this->assertSame(['RECENT-TICKET'], $activity['recent_tickets']->pluck('number')->all());
        $this->assertCount(7, $response->viewData('finance')['trend']);
        $this->get('https://admin.localhost/dashboard?period=1')->assertViewHas('finance', fn ($finance) => $finance['trend']->count() === 1);
        $this->get('https://admin.localhost/dashboard?period=30')->assertViewHas('activity', fn ($activity) => $activity['machines_count'] === 2 && $activity['customers_count'] === 2);
    }

    public function test_drilldowns_preserve_creation_and_activity_ranges(): void
    {
        $old = Customer::factory()->create(['created_at' => now()->subDays(10)]);
        $vm = $this->failedVm('old-vm');
        $vm->forceFill(['created_at' => now()->subDays(10)])->save();
        Ticket::create(['number' => 'ACTIVE-TICKET', 'customer_id' => $old->id, 'subject' => 'Updated ticket', 'status' => Ticket::STATUS_CLOSED, 'priority' => Ticket::PRIORITY_NORMAL, 'created_at' => now()->subDays(10), 'last_activity_at' => now()]);
        Ticket::where('number', 'ACTIVE-TICKET')->firstOrFail()->forceFill(['created_at' => now()->subDays(10)])->save();
        $range = '?from='.today()->toDateString().'&to='.today()->toDateString();
        $this->get('https://admin.localhost/customers'.$range)->assertOk()->assertViewHas('customers', fn ($customers) => $customers->getCollection()->modelKeys() === [$this->customer->id]);
        $this->get('https://admin.localhost/virtual-machines'.$range)->assertOk()->assertViewHas('vms', fn ($vms) => $vms->isEmpty());
        $this->get('https://admin.localhost/tickets'.$range.'&date_basis=activity')->assertOk()->assertViewHas('tickets', fn ($tickets) => $tickets->getCollection()->pluck('number')->all() === ['ACTIVE-TICKET']);
        $this->get('https://admin.localhost/tickets'.$range)->assertOk()->assertViewHas('tickets', fn ($tickets) => $tickets->isEmpty());
    }

    public function test_overview_groups_repeated_failures_and_limits_recent_lists(): void
    {
        foreach (range(1, 10) as $number) {
            $this->failedVm('group-machine-'.$number);
            Customer::factory()->create();
        }
        $response = $this->get('https://admin.localhost/dashboard')->assertOk();
        $this->assertCount(1, $response->viewData('dashboard')['groups']);
        $this->assertSame(10, $response->viewData('dashboard')['groups']->first()['group_count']);
        $this->assertCount(5, $response->viewData('activity')['machines']);
        $this->assertCount(5, $response->viewData('activity')['customers']);
        $this->get('https://admin.localhost/dashboard?queue=1')->assertViewHas('expandedQueue', true);
    }

    public function test_snooze_expires_tomorrow_and_individual_restore_is_scoped(): void
    {
        $this->failedVm('snoozed');
        $this->failedVm('dismissed');
        $items = $this->get('https://admin.localhost/dashboard')->viewData('dashboard')['items'];
        $first = $items[0]['key'];
        $second = $items[1]['key'];
        $this->post('https://admin.localhost/dashboard/warnings/dismiss', ['warning_key' => $first, 'mode' => 'snooze'])->assertRedirect()->assertSessionHas('dismissed_warning_key', $first);
        $this->post('https://admin.localhost/dashboard/warnings/dismiss', ['warning_key' => $second])->assertRedirect();
        $this->get('https://admin.localhost/dashboard?hidden=1')->assertViewHas('dashboard', fn ($dashboard) => $dashboard['hidden_count'] === 2 && $dashboard['items']->count() === 2);
        $this->travelTo(now()->addDay()->startOfDay());
        $this->get('https://admin.localhost/dashboard')->assertViewHas('dashboard', fn ($dashboard) => $dashboard['hidden_count'] === 1 && $dashboard['items']->first()['key'] === $first);
        $otherAdmin = User::factory()->create();
        $this->actingAs($otherAdmin, 'admin')->delete('https://admin.localhost/dashboard/warnings/dismissals', ['warning_key' => $second])->assertRedirect();
        $this->assertDatabaseHas('admin_dashboard_warning_dismissals', ['warning_key' => $second]);
    }

    public function test_individual_restore_keeps_other_dismissals(): void
    {
        $this->failedVm('first');
        $this->failedVm('second');
        $items = $this->get('https://admin.localhost/dashboard')->viewData('dashboard')['items'];
        foreach ($items as $item) {
            $this->post('https://admin.localhost/dashboard/warnings/dismiss', ['warning_key' => $item['key']]);
        }
        $this->delete('https://admin.localhost/dashboard/warnings/dismissals', ['warning_key' => $items[0]['key']])->assertRedirect();
        $this->get('https://admin.localhost/dashboard')->assertViewHas('dashboard', fn ($dashboard) => $dashboard['hidden_count'] === 1 && $dashboard['items']->first()['key'] === $items[0]['key']);
    }

    public function test_warning_rules_can_be_disabled_and_restored_only_for_current_admin(): void
    {
        $admin = auth('admin')->user();
        $this->failedVm('muted-machine');
        $this->patch('https://admin.localhost/dashboard/warnings/preferences', ['warning_type' => 'vm-provisioning', 'muted' => 1])->assertRedirect();
        $this->get('https://admin.localhost/dashboard')->assertViewHas('dashboard', fn ($dashboard) => $dashboard['total_count'] === 0);
        $this->failedVm('future-muted-machine');
        $this->get('https://admin.localhost/dashboard')->assertViewHas('dashboard', fn ($dashboard) => $dashboard['total_count'] === 0);
        $this->actingAs(User::factory()->create(), 'admin')->get('https://admin.localhost/dashboard')->assertViewHas('dashboard', fn ($dashboard) => $dashboard['total_count'] === 2);
        $this->actingAs($admin, 'admin')->patch('https://admin.localhost/dashboard/warnings/preferences', ['warning_type' => 'vm-provisioning', 'muted' => 0])->assertRedirect();
        $this->get('https://admin.localhost/dashboard')->assertViewHas('dashboard', fn ($dashboard) => $dashboard['total_count'] === 2);
    }

    public function test_recurring_provisioning_issue_returns_after_a_new_retry(): void
    {
        $vm = $this->failedVm('recurring-machine');
        $key = $this->get('https://admin.localhost/dashboard')->viewData('dashboard')['items']->first()['key'];
        $this->post('https://admin.localhost/dashboard/warnings/dismiss', ['warning_key' => $key]);
        $vm->update(['provisioning_status' => VirtualMachine::PROVISION_PENDING, 'remote_state' => ['retry_queued_at' => now()->toIso8601String()]]);
        $this->get('https://admin.localhost/dashboard')->assertViewHas('dashboard', fn ($dashboard) => $dashboard['total_count'] === 0);
        $vm->update(['provisioning_status' => VirtualMachine::PROVISION_FAILED]);
        $this->get('https://admin.localhost/dashboard')->assertViewHas('dashboard', fn ($dashboard) => $dashboard['hidden_count'] === 0 && $dashboard['items']->first()['key'] !== $key);
        $this->assertDatabaseHas('virtual_machines', ['id' => $vm->id]);
    }

    public function test_muting_provisioning_does_not_remove_backup_category(): void
    {
        $vm = $this->failedVm('backup-category');
        $vm->backups()->create(['status' => VmBackup::STATUS_FAILED, 'source' => VmBackup::SOURCE_MANUAL]);
        $this->patch('https://admin.localhost/dashboard/warnings/preferences', ['warning_type' => 'vm-provisioning', 'muted' => 1]);
        $this->get('https://admin.localhost/dashboard')->assertViewHas('dashboard', fn ($dashboard) => collect($dashboard['health'])->firstWhere('category', 'machines')['count'] === 1);
    }

    public function test_dashboard_activity_and_warning_preferences_respect_permissions(): void
    {
        $admin = User::factory()->create(['role' => AdminRole::Custom, 'permissions' => ['dashboard.view', 'tickets.read']]);
        $this->actingAs($admin, 'admin')->get('https://admin.localhost/dashboard')->assertOk()
            ->assertViewHas('finance', null)
            ->assertViewHas('activity', fn ($activity) => $activity['machines'] === null && $activity['customers'] === null && $activity['machines_count'] === null)
            ->assertDontSee('آخرین مشتریان')->assertDontSee('آخرین ماشین‌های مجازی');
        $this->patch('https://admin.localhost/dashboard/warnings/preferences', ['warning_type' => 'vm-provisioning', 'muted' => 1])->assertSessionHasErrors('warning_type');
    }

    private function payment(string $status, int $amount, string $currency, string $provider): Payment
    {
        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'wallet_id' => $this->customer->wallet->id,
            'provider' => $provider,
            'type' => Payment::TYPE_TOP_UP,
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'paid_at' => $status === Payment::STATUS_SUCCESSFUL ? now() : null,
        ]);
        $payment->update(['provider_reference' => 'REF-'.$payment->id]);

        return $payment;
    }

    private function failedVm(string $name): VirtualMachine
    {
        return VirtualMachine::create([
            'customer_id' => $this->customer->id,
            'project_id' => $this->customer->ensureDefaultProject()->id,
            'name' => $name,
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_STOPPED,
            'provisioning_status' => VirtualMachine::PROVISION_FAILED,
        ]);
    }
}
