<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Models\WalletTransaction;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\UsageBillingService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CustomerWalletBillingTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_can_complete_dummy_top_up_flow_once(): void
    {
        $customer = Customer::factory()->create();
        $payment = app(PaymentService::class)->createTopUp($customer, 200000, 'شارژ کیف پول توسط مشتری');

        $this->actingAs($customer, 'customer');
        $this->get($this->customerBaseUrl.'/wallet/payments/'.$payment->id.'/gateway')
            ->assertOk()
            ->assertSee('Dummy Gateway');

        $this->post($this->customerBaseUrl.'/wallet/payments/'.$payment->id.'/gateway')
            ->assertRedirect($this->customerBaseUrl.'/wallet');

        $this->post($this->customerBaseUrl.'/wallet/payments/'.$payment->id.'/gateway')
            ->assertRedirect($this->customerBaseUrl.'/wallet');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'successful',
            'amount' => 200000,
        ]);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'type' => WalletTransaction::TYPE_CREDIT,
            'amount' => 200000,
            'reference_type' => 'App\\Models\\Payment',
            'reference_id' => $payment->id,
        ]);

        $this->assertSame(200000, $customer->wallet()->firstOrFail()->balance);
    }

    public function test_wallet_page_shows_transactions_and_filters(): void
    {
        $customer = Customer::factory()->create();
        $wallets = app(WalletService::class);

        $wallets->credit($customer, 150000, 'شارژ اولیه');
        $wallets->charge($customer, 45000, 'کسر PAYG', metadata: [
            'category' => 'payg_usage',
            'vm_name' => 'vm-alpha',
            'period_start' => now()->subHour()->toIso8601String(),
            'period_end' => now()->toIso8601String(),
            'hours' => 1,
            'hourly_rate' => 45000,
        ]);

        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/wallet')
            ->assertOk()
            ->assertSee('شارژ اولیه')
            ->assertSee('کسر PAYG');

        $this->get($this->customerBaseUrl.'/wallet?type=charge')
            ->assertOk()
            ->assertSee('کسر PAYG')
            ->assertDontSee('شارژ اولیه');
    }

    public function test_usage_charge_command_creates_wallet_charge_and_updates_vm_checkpoint(): void
    {
        CarbonImmutable::setTestNow('2026-06-15 12:00:00');

        $customer = Customer::factory()->create();
        $bundle = VmBundle::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'monthly_price' => 730000,
            'hourly_price' => 1000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'vm-billable',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => now()->subHours(3),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        Artisan::call('billing:charge-usage');

        $vm->refresh();
        $transaction = $customer->walletTransactions()->first();

        $this->assertNotNull($transaction);
        $this->assertSame(WalletTransaction::TYPE_CHARGE, $transaction->type);
        $this->assertSame(-3000, $transaction->amount);
        $this->assertSame(-3000, $customer->wallet()->firstOrFail()->balance);
        $this->assertSame('payg_usage', $transaction->metadata['category']);
        $this->assertSame('vm-billable', $transaction->metadata['vm_name']);
        $this->assertTrue($vm->last_billed_at->equalTo(now()));

        CarbonImmutable::setTestNow();
    }

    public function test_deleted_vm_is_not_counted_in_wallet_pending_usage(): void
    {
        CarbonImmutable::setTestNow('2026-06-15 12:00:00');

        $customer = Customer::factory()->create();
        $bundle = VmBundle::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'monthly_price' => 730000,
            'hourly_price' => 1000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'barooneh',
            'cpu_cores' => 4,
            'ram_gb' => 8,
            'disk_gb' => 75,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_DELETED,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => now()->subHours(3),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->assertSame(0, app(UsageBillingService::class)->estimateVmUsage($vm)['amount']);
        $this->assertSame(0, app(UsageBillingService::class)->customerPendingUsage($customer));

        $this->actingAs($customer, 'customer');
        $this->get($this->customerBaseUrl.'/wallet')
            ->assertOk()
            ->assertSee('0 تومان')
            ->assertDontSee('300 تومان');

        CarbonImmutable::setTestNow();
    }

    public function test_usage_charge_command_skips_deleted_vm(): void
    {
        CarbonImmutable::setTestNow('2026-06-15 12:00:00');

        $customer = Customer::factory()->create();
        $bundle = VmBundle::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'monthly_price' => 730000,
            'hourly_price' => 1000,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $lastBilledAt = now()->subHours(3);

        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'deleted-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_DELETED,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => $lastBilledAt,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        Artisan::call('billing:charge-usage');

        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(0, $customer->wallet()->firstOrFail()->balance);
        $this->assertTrue($vm->refresh()->last_billed_at->equalTo($lastBilledAt));

        CarbonImmutable::setTestNow();
    }

    public function test_monthly_invoice_command_generates_per_vm_invoice(): void
    {
        CarbonImmutable::setTestNow('2026-07-03 10:00:00');

        $customer = Customer::factory()->create();
        $wallets = app(WalletService::class);
        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'name' => 'vm-invoice',
            'cpu_cores' => 4,
            'ram_gb' => 8,
            'disk_gb' => 120,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        [$periodStart, $periodEnd] = app(InvoiceService::class)->previousMonthPeriod();
        $transaction = $wallets->charge($customer, 120000, 'کسر کارکرد PAYG برای VM vm-invoice', metadata: [
            'category' => 'payg_usage',
            'vm_id' => $vm->id,
            'vm_name' => $vm->name,
            'period_start' => $periodStart->copy()->addDay()->toIso8601String(),
            'period_end' => $periodStart->copy()->addDays(2)->toIso8601String(),
            'hours' => 24,
            'hourly_rate' => 5000,
            'resource_snapshot' => [
                'cpu_cores' => 4,
                'ram_gb' => 8,
                'disk_gb' => 120,
                'ip_count' => 1,
            ],
        ]);
        $transaction->forceFill([
            'created_at' => $periodStart->copy()->addDays(2),
            'updated_at' => $periodStart->copy()->addDays(2),
        ])->save();

        Artisan::call('billing:generate-monthly-invoices');
        Artisan::call('billing:generate-monthly-invoices');

        $invoice = Invoice::query()->where('customer_id', $customer->id)->first();

        $this->assertNotNull($invoice);
        $this->assertSame('INV-'.$periodStart->format('Ym').'-'.str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT), $invoice->number);
        $this->assertSame(120000, $invoice->total_amount);
        $this->assertCount(1, $invoice->items);
        $this->assertSame('vm-invoice', $invoice->items->first()->label);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_items', 1);

        $this->actingAs($customer, 'customer');
        $this->get($this->customerBaseUrl.'/invoices')
            ->assertOk()
            ->assertSee($invoice->number);
        $this->get($this->customerBaseUrl.'/invoices/'.$invoice->id)
            ->assertOk()
            ->assertSee('vm-invoice')
            ->assertSee($invoice->number);

        CarbonImmutable::setTestNow();
    }
}
