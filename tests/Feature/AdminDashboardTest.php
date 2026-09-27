<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
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
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['health'][2]['count'] === 1);

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
