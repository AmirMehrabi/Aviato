<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\UsageSettlement;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBillingCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portals.admin.domain' => 'admin.localhost', 'portals.customer.domain' => 'cp.localhost']);
        $this->admin = User::factory()->create();
        $this->customer = Customer::factory()->create(['name' => 'Billing Customer']);
        $this->actingAs($this->admin, 'admin');
    }

    public function test_admin_can_view_real_billing_overview_and_ledgers(): void
    {
        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'wallet_id' => $this->customer->wallet->id,
            'provider' => 'mellat',
            'type' => Payment::TYPE_TOP_UP,
            'status' => Payment::STATUS_SUCCESSFUL,
            'amount' => 2_500_000,
            'currency' => 'IRR',
            'authority' => 'AUTH-123',
            'provider_reference' => 'REF-456',
            'gateway_payload' => ['card_pan' => '6037991234567890', 'result' => 'ok'],
            'paid_at' => now(),
        ]);
        app(WalletService::class)->credit($this->customer, $payment->amount, 'Gateway top up', reference: $payment);
        UsageSettlement::create([
            'customer_id' => $this->customer->id,
            'scope_key' => 'customer:'.$this->customer->id,
            'service_date' => today(),
            'amount' => 500_000,
            'settled_at' => now(),
        ]);

        $this->get('https://admin.localhost/billing')
            ->assertOk()->assertSee('مرکز مالی')->assertSee('Billing Customer')->assertSee('وصول وجه و مصرف');
        $this->get('https://admin.localhost/billing/payments?q=AUTH-123')
            ->assertOk()->assertSee('AUTH-123')->assertSee('Billing Customer');
        $this->get('https://admin.localhost/billing/payments/'.$payment->id)
            ->assertOk()->assertSee('REF-456')->assertDontSee('6037991234567890')->assertSee('603******');
        $this->get('https://admin.localhost/billing/transactions')
            ->assertOk()->assertSee('Gateway top up');
        $this->get('https://admin.localhost/billing/usage')
            ->assertOk()->assertSee('مصرف و تسویه');
        $this->get('https://admin.localhost/billing/wallets')
            ->assertOk()->assertSee('Billing Customer');
    }

    public function test_admin_dashboard_surfaces_gateway_payment_metrics_and_recent_payments(): void
    {
        Payment::create([
            'customer_id' => $this->customer->id,
            'wallet_id' => $this->customer->wallet->id,
            'provider' => 'mellat',
            'type' => Payment::TYPE_TOP_UP,
            'status' => Payment::STATUS_SUCCESSFUL,
            'amount' => 2_500_000,
            'currency' => 'IRR',
            'authority' => 'AUTH-DASHBOARD',
            'provider_reference' => 'REF-DASHBOARD',
            'paid_at' => now(),
        ]);
        Payment::create([
            'customer_id' => $this->customer->id,
            'wallet_id' => $this->customer->wallet->id,
            'provider' => 'mellat',
            'type' => Payment::TYPE_TOP_UP,
            'status' => Payment::STATUS_PENDING,
            'amount' => 1_000_000,
            'currency' => 'IRR',
            'authority' => 'AUTH-PENDING',
        ]);

        $this->get('https://admin.localhost/dashboard')
            ->assertOk()
            ->assertSee('پرداخت‌های درگاه')
            ->assertSee('وصول موفق')
            ->assertSee('REF-DASHBOARD')
            ->assertSee('Billing Customer')
            ->assertSee('در انتظار');
    }

    public function test_admin_can_act_on_dismiss_and_restore_dashboard_warnings(): void
    {
        $vm = VirtualMachine::create([
            'customer_id' => $this->customer->id,
            'project_id' => $this->customer->ensureDefaultProject()->id,
            'name' => 'failed-dashboard-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_STOPPED,
            'provisioning_status' => VirtualMachine::PROVISION_FAILED,
        ]);

        $response = $this->get('https://admin.localhost/dashboard')
            ->assertOk()
            ->assertSee('هشدارهای بحرانی')
            ->assertSee('failed-dashboard-vm')
            ->assertSee('تلاش دوباره')
            ->assertSee(route('admin.virtual-machines.retry-provisioning', $vm));

        preg_match('/name="warning_key" value="([a-f0-9]{64})"/', $response->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);

        $this->post('https://admin.localhost/dashboard/warnings/dismiss', [
            'warning_key' => $matches[1],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('admin_dashboard_warning_dismissals', [
            'user_id' => $this->admin->id,
            'warning_key' => $matches[1],
        ]);
        $this->get('https://admin.localhost/dashboard')
            ->assertOk()
            ->assertDontSee('failed-dashboard-vm')
            ->assertSee('نمایش 1 هشدار بسته‌شده');

        $this->delete('https://admin.localhost/dashboard/warnings/dismissals')
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('admin_dashboard_warning_dismissals', [
            'user_id' => $this->admin->id,
        ]);
        $this->get('https://admin.localhost/dashboard')
            ->assertOk()
            ->assertSee('failed-dashboard-vm');
    }

    public function test_admin_can_view_printable_invoice_and_export_csv(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'number' => 'INV-TEST-001',
            'status' => Invoice::STATUS_ISSUED,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issued_at' => now(),
            'currency' => 'IRR',
            'subtotal_amount' => 1_000_000,
            'wallet_charged_amount' => 1_000_000,
            'adjustment_amount' => 0,
            'total_amount' => 1_000_000,
            'tax_amount' => 0,
            'tax_rate_percentage' => 0,
        ]);
        $invoice->items()->create([
            'type' => 'vm_usage', 'label' => 'production-vm', 'quantity' => 10,
            'unit' => 'hour', 'unit_price' => 100_000, 'subtotal' => 1_000_000,
        ]);

        $this->get('https://admin.localhost/billing/invoices/'.$invoice->id)
            ->assertOk()->assertSee('INV-TEST-001')->assertSee('production-vm')->assertSee('چاپ / ذخیره PDF');

        $this->get('https://admin.localhost/billing/exports/invoices')
            ->assertOk()->assertDownload()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_customer_cannot_access_admin_billing_routes(): void
    {
        auth('admin')->logout();

        $this->get('https://admin.localhost/billing')->assertRedirect();
    }
}
