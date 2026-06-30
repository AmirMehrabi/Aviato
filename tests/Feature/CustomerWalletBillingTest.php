<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Models\WalletTransaction;
use App\Services\InvoiceService;
use App\Services\Payments\HesabroPaymentException;
use App\Services\Payments\MellatClientInterface;
use App\Services\PaymentService;
use App\Services\ProxmoxService;
use App\Services\UsageBillingService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class CustomerWalletBillingTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_can_complete_mellat_top_up_flow_once(): void
    {
        $customer = Customer::factory()->create();
        $this->enableMellatGateway();
        $this->fakeMellatClient();

        $payment = app(PaymentService::class)->createTopUp($customer, 200000, 'شارژ کیف پول توسط مشتری');
        $payment->refresh();

        $this->actingAs($customer, 'customer');
        $this->get($this->customerBaseUrl.'/wallet/payments/'.$payment->id.'/gateway')
            ->assertOk()
            ->assertSee('Mellat')
            ->assertSee($payment->authority);

        $this->post($this->customerBaseUrl.'/wallet/payments/'.$payment->id.'/callback', [
            'RefId' => $payment->authority,
            'ResCode' => '0',
            'SaleOrderId' => $payment->id,
            'SaleReferenceId' => '127926981246',
        ])
            ->assertRedirect($this->customerBaseUrl.'/wallet?payment_id='.$payment->id)
            ->assertCookieMissing(config('session.cookie'));

        $this->get($this->customerBaseUrl.'/wallet?payment_id='.$payment->id)
            ->assertOk()
            ->assertSee('پرداخت با موفقیت تایید شد و کیف پول شما شارژ شد.');

        $this->post($this->customerBaseUrl.'/wallet/payments/'.$payment->id.'/callback', [
            'RefId' => $payment->authority,
            'ResCode' => '0',
            'SaleOrderId' => $payment->id,
            'SaleReferenceId' => '127926981246',
        ])
            ->assertRedirect($this->customerBaseUrl.'/wallet?payment_id='.$payment->id);

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

    public function test_mellat_callback_mismatch_fails_without_crediting_wallet(): void
    {
        $customer = Customer::factory()->create();
        $this->enableMellatGateway();
        $this->fakeMellatClient();
        $payment = app(PaymentService::class)->createTopUp($customer, 1000000, 'شارژ کیف پول توسط مشتری')->refresh();

        $this->post($this->customerBaseUrl.'/wallet/payments/'.$payment->id.'/callback', [
            'RefId' => 'WRONG-'.$payment->authority,
            'ResCode' => '0',
            'SaleOrderId' => $payment->id,
            'SaleReferenceId' => '127926981246',
        ])->assertRedirect($this->customerBaseUrl.'/wallet?payment_id='.$payment->id);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_FAILED,
        ]);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(0, $customer->wallet()->firstOrFail()->balance);
    }

    public function test_wallet_top_up_converts_toman_amount_to_rials(): void
    {
        $customer = Customer::factory()->create();
        $this->enableMellatGateway();
        $this->fakeMellatClient();

        $this->actingAs($customer, 'customer')
            ->post($this->customerBaseUrl.'/wallet/top-ups', [
                'amount_toman' => 300000,
                'gateway' => 'mellat',
            ])
            ->assertRedirect($this->customerBaseUrl.'/wallet/payments/1/gateway');

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'provider' => 'mellat',
            'status' => Payment::STATUS_PENDING,
            'amount' => 3000000,
            'currency' => 'IRR',
        ]);
    }

    public function test_wallet_top_up_normalizes_persian_digits_and_separators(): void
    {
        $customer = Customer::factory()->create();
        $this->enableMellatGateway();
        $this->fakeMellatClient();

        $this->actingAs($customer, 'customer')
            ->post($this->customerBaseUrl.'/wallet/top-ups', [
                'amount_toman' => '۱٬۲۵۰٬۰۰۰',
                'gateway' => 'mellat',
            ])
            ->assertRedirect($this->customerBaseUrl.'/wallet/payments/1/gateway');

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'amount' => 12500000,
        ]);
    }

    public function test_wallet_top_up_rejects_amount_below_minimum(): void
    {
        $customer = Customer::factory()->create();
        $this->enableMellatGateway();
        $this->fakeMellatClient();

        $this->actingAs($customer, 'customer')
            ->from($this->customerBaseUrl.'/wallet')
            ->post($this->customerBaseUrl.'/wallet/top-ups', [
                'amount_toman' => 99999,
                'gateway' => 'mellat',
            ])
            ->assertRedirect($this->customerBaseUrl.'/wallet')
            ->assertSessionHasErrors('amount_toman');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_wallet_page_shows_requested_mellat_top_up_presets(): void
    {
        $customer = Customer::factory()->create();
        $this->enableMellatGateway();

        $this->actingAs($customer, 'customer')
            ->get($this->customerBaseUrl.'/wallet')
            ->assertOk()
            ->assertSee('100,000')
            ->assertSee('300,000')
            ->assertSee('1,000,000')
            ->assertSee('2,500,000')
            // ->assertSee('تمام مبلغ‌ها به تومان است')
            ->assertSee('مبلغ دلخواه (تومان)')
            ->assertSee('پرداخت و افزایش موجودی')
            ->assertDontSee('انتخاب درگاه پرداخت')
            ->assertSee('type="hidden" name="gateway" value="mellat"', false);
    }

    public function test_wallet_page_shows_gateway_selector_when_multiple_gateways_are_available(): void
    {
        $customer = Customer::factory()->create();
        $this->enableMellatGateway();
        $this->enableHesabroGateway();

        $this->actingAs($customer, 'customer')
            ->get($this->customerBaseUrl.'/wallet')
            ->assertOk()
            ->assertSee('انتخاب درگاه پرداخت')
            ->assertSee('بانک ملت')
            ->assertSee('حسابرو');
    }

    public function test_customer_can_choose_hesabro_and_receive_payment_link(): void
    {
        $customer = Customer::factory()->create(['phone' => '09123456789']);
        $this->enableHesabroGateway();

        Http::fake(fn ($request) => Http::response([
            'amount' => 1000000,
            'callback_url' => $request['callback_url'],
            'go_to_ipg_url' => 'https://payments.example/pay/token/123456',
        ]));

        $payment = app(PaymentService::class)->createTopUp(
            $customer,
            1000000,
            'شارژ کیف پول توسط مشتری',
            'hesabro',
        );

        $this->assertSame('hesabro', $payment->provider);
        $this->assertSame('09123456789', $payment->gateway_payload['username']);
        $this->assertSame('https://payments.example/pay/token/123456', $payment->gateway_payload['redirect_url']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.hesabro.ir/@sabz-co/payment-service/wallet/user-charge?username=09123456789'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret'))
            && $request['amount'] === 1000000
            && str_ends_with($request['callback_url'], '/wallet/payments/1/callback'));
    }

    public function test_hesabro_charge_rejects_mismatched_response(): void
    {
        $customer = Customer::factory()->create(['phone' => '09123456789']);
        $this->enableHesabroGateway();

        Http::fake(fn ($request) => Http::response([
            'amount' => 900000,
            'callback_url' => $request['callback_url'],
            'go_to_ipg_url' => 'https://payments.example/pay/token/123456',
        ]));

        try {
            app(PaymentService::class)->createTopUp($customer, 1000000, provider: 'hesabro');
            $this->fail('The mismatched Hesabro charge response should be rejected.');
        } catch (HesabroPaymentException $exception) {
            $this->assertStringContainsString('همخوانی ندارد', $exception->getMessage());
        }

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'provider' => 'hesabro',
            'status' => Payment::STATUS_FAILED,
        ]);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_hesabro_callback_credits_wallet_on_success(): void
    {
        $customer = Customer::factory()->create();
        $this->enableHesabroGateway();
        $payment = Payment::create([
            'customer_id' => $customer->id,
            'wallet_id' => app(WalletService::class)->walletFor($customer)->id,
            'provider' => 'hesabro',
            'type' => Payment::TYPE_TOP_UP,
            'status' => Payment::STATUS_PENDING,
            'amount' => 1000000,
            'currency' => 'IRR',
            'authority' => '987',
            'gateway_payload' => ['order_id' => '987'],
        ]);

        $this->post($this->customerBaseUrl.'/wallet/payments/'.$payment->id.'/callback', [
            'order_id' => '987',
            'status' => 'success',
        ])->assertRedirect($this->customerBaseUrl.'/wallet?payment_id='.$payment->id);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_SUCCESSFUL, $payment->status);
        $this->assertSame('987', $payment->gateway_payload['callback']['order_id']);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(1000000, $customer->wallet()->firstOrFail()->balance);
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

    public function test_usage_charge_command_accrues_hourly_and_daily_settlement_charges_once(): void
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
        $this->assertDatabaseHas('usage_accruals', [
            'customer_id' => $customer->id,
            'resource_type' => 'virtual_machine',
            'resource_id' => $vm->id,
            'amount' => 3000,
        ]);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(0, $customer->wallet()->firstOrFail()->balance);
        $this->assertSame(3000, app(UsageBillingService::class)->customerPendingUsage($customer));
        $this->assertTrue(app(WalletService::class)->isBelowNegativeThreshold($customer));
        $this->assertTrue($vm->last_billed_at->equalTo(now()));

        Artisan::call('billing:settle-usage', ['--date' => now()->toDateString()]);
        Artisan::call('billing:settle-usage', ['--date' => now()->toDateString()]);

        $transaction = $customer->walletTransactions()->firstOrFail();
        $this->assertSame(WalletTransaction::TYPE_CHARGE, $transaction->type);
        $this->assertSame(-3000, $transaction->amount);
        $this->assertSame(-3000, $customer->wallet()->firstOrFail()->balance);
        $this->assertSame('usage_settlement', $transaction->metadata['category']);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(0, app(UsageBillingService::class)->customerPendingUsage($customer));

        CarbonImmutable::setTestNow();
    }

    public function test_daily_settlement_batches_multiple_vms_in_one_project(): void
    {
        CarbonImmutable::setTestNow('2026-06-15 12:00:00');
        $customer = Customer::factory()->create();
        $project = $customer->ensureDefaultProject();
        $bundle = VmBundle::create([
            'name' => 'Batch',
            'slug' => 'batch',
            'cpu_cores' => 1,
            'ram_gb' => 1,
            'disk_gb' => 20,
            'ip_count' => 1,
            'monthly_price' => 730000,
            'hourly_price' => 1000,
            'is_active' => true,
        ]);

        foreach (['batch-a', 'batch-b'] as $name) {
            VirtualMachine::create([
                'customer_id' => $customer->id,
                'project_id' => $project->id,
                'vm_bundle_id' => $bundle->id,
                'name' => $name,
                'cpu_cores' => 1,
                'ram_gb' => 1,
                'disk_gb' => 20,
                'ip_count' => 1,
                'status' => VirtualMachine::STATUS_RUNNING,
                'provisioning_status' => VirtualMachine::PROVISION_READY,
                'last_billed_at' => now()->subHour(),
            ]);
        }

        app(UsageBillingService::class)->accrueAllDueUsage();
        app(UsageBillingService::class)->settleDate(now());

        $this->assertDatabaseCount('usage_accruals', 2);
        $this->assertDatabaseCount('usage_settlements', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(-2000, $customer->wallet()->firstOrFail()->balance);

        CarbonImmutable::setTestNow();
    }

    public function test_monthly_invoice_uses_accrual_service_date_and_purges_invoiced_detail(): void
    {
        CarbonImmutable::setTestNow('2026-06-30 23:00:00');
        $customer = Customer::factory()->create();
        $bundle = VmBundle::create([
            'name' => 'Month End',
            'slug' => 'month-end',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'monthly_price' => 730000,
            'hourly_price' => 1000,
            'is_active' => true,
        ]);
        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'project_id' => $customer->ensureDefaultProject()->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'month-end-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => now()->subHour(),
        ]);

        app(UsageBillingService::class)->accrueVm($vm);

        CarbonImmutable::setTestNow('2026-07-01 00:05:00');
        app(UsageBillingService::class)->settleDate('2026-06-30');
        $this->assertDatabaseHas('usage_accruals', [
            'customer_id' => $customer->id,
            'service_date' => '2026-06-30 00:00:00',
            'amount' => 1000,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'amount' => -1000,
        ]);

        CarbonImmutable::setTestNow('2026-07-03 10:00:00');
        [$periodStart, $periodEnd] = app(InvoiceService::class)->previousMonthPeriod();
        $this->assertSame('2026-06-01', $periodStart->toDateString());
        $this->assertSame('2026-06-30', $periodEnd->toDateString());
        $this->assertNotNull(DB::table('usage_accruals')->value('settled_at'));
        $invoice = app(InvoiceService::class)->generateForCustomer($customer, $periodStart, $periodEnd);

        $this->assertNotNull($invoice);
        $this->assertSame(1000, $invoice->total_amount);
        $this->assertSame('month-end-vm', $invoice->items()->firstOrFail()->label);
        $this->assertDatabaseCount('usage_accruals', 0);

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

    public function test_negative_wallet_locks_vms_and_blocks_them_until_top_up(): void
    {
        $customer = Customer::factory()->create();
        $server = ProxmoxServer::create([
            'name' => 'THR Proxmox',
            'datacenter' => 'THR-1',
            'host' => 'pve.local',
            'port' => 8006,
            'realm' => 'pam',
            'username' => 'root',
            'api_token_id' => 'root@pam!panel',
            'api_token_secret' => 'secret',
            'is_active' => true,
            'maintenance_mode' => false,
        ]);
        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $server->id,
            'vmid' => 101,
            'name' => 'wallet-locked-vm',
            'hostname' => 'wallet-locked-vm',
            'node' => 'pve1',
            'storage' => 'local-lvm',
            'network_bridge' => 'vmbr1',
            'ip_address' => '192.168.10.50',
            'login_username' => 'ubuntu',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->mock(ProxmoxService::class, function ($mock) use ($customer, $server, $vm): void {
            $mock->shouldReceive('stopVm')
                ->once()
                ->with(
                    Mockery::on(fn ($value) => $value instanceof ProxmoxServer && $value->is($server)),
                    'pve1',
                    101,
                    Mockery::on(fn (array $context): bool => $context['source'] === 'wallet_suspension'
                        && $context['virtual_machine_id'] === $vm->id
                        && $context['customer_id'] === $customer->id),
                )
                ->andReturn(['task_id' => 'UPID:stop']);
        });

        app(WalletService::class)->charge($customer, 1000, 'کسر آزمایشی');

        $vm->refresh();
        $this->assertSame(VirtualMachine::STATUS_SUSPENDED, $vm->status);
        $this->assertNotNull(data_get($vm->remote_state, 'wallet_locked_at'));

        $this->actingAs($customer, 'customer');
        $this->get($this->customerBaseUrl.'/dashboard')
            ->assertRedirect($this->customerBaseUrl.'/suspended');

        app(WalletService::class)->credit($customer, 2000, 'شارژ آزمایشی');

        $vm->refresh();
        $this->assertSame(VirtualMachine::STATUS_STOPPED, $vm->status);
        $this->assertNull(data_get($vm->remote_state, 'wallet_locked_at'));
        $this->assertNotNull(data_get($vm->remote_state, 'wallet_unlocked_at'));
    }

    private function enableMellatGateway(): void
    {
        AppSetting::setValue(AppSetting::PAYMENTS_ENABLED, true, 'boolean', 'payment');
        AppSetting::setValue(AppSetting::DEFAULT_PAYMENT_GATEWAY, 'mellat', 'string', 'payment');
        AppSetting::setValue(AppSetting::MELLAT_PAYMENT_ENABLED, true, 'boolean', 'payment');
        AppSetting::setValue(AppSetting::MELLAT_PAYMENT_MODE, 'test', 'string', 'payment');
        AppSetting::setValue(AppSetting::MELLAT_TERMINAL_ID, '1234', 'string', 'payment');
        AppSetting::setValue(AppSetting::MELLAT_USERNAME, 'merchant', 'string', 'payment');
        AppSetting::setValue(AppSetting::MELLAT_PASSWORD, 'secret', 'string', 'payment');
    }

    private function enableHesabroGateway(): void
    {
        AppSetting::setValue(AppSetting::PAYMENTS_ENABLED, true, 'boolean', 'payment');
        AppSetting::setValue(AppSetting::DEFAULT_PAYMENT_GATEWAY, 'hesabro', 'string', 'payment');
        AppSetting::setValue(AppSetting::HESABRO_PAYMENT_ENABLED, true, 'boolean', 'payment');
        AppSetting::setValue(AppSetting::HESABRO_CLIENT, 'sabz-co', 'string', 'payment');
        AppSetting::setValue(AppSetting::HESABRO_CLIENT_ID, 'client-id', 'string', 'payment');
        AppSetting::setValue(AppSetting::HESABRO_CLIENT_SECRET, 'client-secret', 'string', 'payment');
    }

    private function fakeMellatClient(): void
    {
        $this->app->bind(MellatClientInterface::class, fn (): MellatClientInterface => new class implements MellatClientInterface
        {
            public function bpPayRequest(array $parameters): string
            {
                return '0,REF-'.$parameters['orderId'];
            }

            public function bpVerifySettleRequest(array $parameters): string
            {
                return '0';
            }

            public function bpSettleRequest(array $parameters): string
            {
                return '0';
            }
        });
    }
}
