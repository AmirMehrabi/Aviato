<?php

namespace Tests\Feature;

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
            ->assertDontSee('Waiting for customer')
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['health'][3]['count'] === 1);

        $this->get('https://admin.localhost/dashboard?category=tickets')
            ->assertOk()
            ->assertSee('Urgent response needed')
            ->assertDontSee('critical-machine')
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
        $machines->firstWhere('name', $item['title'])->touch();

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
            ->assertSee('REF-'.($paidToday->id))
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
