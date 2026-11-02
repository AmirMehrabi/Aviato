<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ColocationPlan;
use App\Models\ColocationService;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\ColocationBillingService;
use App\Services\Payments\MellatClientInterface;
use App\Services\PaymentService;
use App\Services\ProjectAccessService;
use App\Services\ProjectLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ColocationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private User $admin;

    private Project $project;

    private ColocationPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00', 'Asia/Tehran'));
        $this->customer = Customer::factory()->create();
        $this->customer->forceFill(['colocation_enabled' => true])->save();
        $this->admin = User::factory()->create();
        $this->project = $this->customer->ensureDefaultProject();
        $this->plan = ColocationPlan::create([
            'customer_id' => $this->customer->id, 'name' => 'Private 2U', 'location' => 'Tehran',
            'rack_units' => 2, 'power' => '300 W', 'connectivity' => '1 Gbps',
            'traffic_policy' => 'included', 'included_traffic_gb' => 1000, 'traffic_price_per_gb' => 10000,
            'currency' => 'IRR', 'monthly_price' => 20000000, 'quarterly_price' => 55000000,
            'yearly_price' => 200000000, 'setup_fee' => 5000000, 'tax_rate' => 10,
            'terms' => 'Monthly notice; renew at agreed prices.', 'delivery_instructions' => 'Coordinate delivery with support.', 'available' => true,
        ]);
        foreach ([AppSetting::PAYMENTS_ENABLED => true, AppSetting::MELLAT_PAYMENT_ENABLED => true, AppSetting::MELLAT_PAYMENT_MODE => 'test', AppSetting::MELLAT_TERMINAL_ID => '1234', AppSetting::MELLAT_USERNAME => 'merchant', AppSetting::MELLAT_PASSWORD => 'test-only'] as $key => $value) {
            AppSetting::setValue($key, $value, is_bool($value) ? 'boolean' : 'string', 'payment');
        }
        $this->app->bind(MellatClientInterface::class, fn () => new class implements MellatClientInterface
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

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_feature_is_private_and_disabled_by_default(): void
    {
        $other = Customer::factory()->create();
        $this->assertFalse((bool) $other->colocation_enabled);
        $this->actingAs($other, 'customer')->get('https://cp.localhost/colocation')->assertNotFound();
        $this->get('https://cp.localhost/colocation/plans/'.$this->plan->uuid)->assertNotFound();
        $this->get('https://cp.localhost/dashboard')->assertOk()->assertDontSee('href="/colocation"', false);
        $this->actingAs($this->customer, 'customer')->get('https://cp.localhost/colocation')->assertOk()->assertSee('Private 2U')->assertSee('هم‌مکانی');
        $this->get('https://cp.localhost/colocation/plans/'.$this->plan->uuid)->assertOk()->assertSee('اولین پرداخت')->assertSee('سه‌ماهه');
        $this->get('https://my.aviato.ir/colocation')->assertOk()->assertSee('Private 2U')->assertDontSee('کیف پول شما خالی است');
    }

    public function test_checkout_is_idempotent_and_preserves_the_agreed_prices(): void
    {
        $this->actingAs($this->customer, 'customer');
        $payload = $this->checkoutData();
        $url = 'https://cp.localhost/colocation/plans/'.$this->plan->uuid;
        $this->post($url, $payload)->assertRedirect();
        $this->post($url, $payload)->assertRedirect();
        $this->assertDatabaseCount('colocation_services', 1);
        $this->assertDatabaseCount('invoices', 1);
        $service = ColocationService::firstOrFail();
        $this->assertSame('quarterly', $service->billing_interval);
        $this->assertNull($service->billing_starts_at);
        $this->assertNull($service->invoices->first()->period_start);
        $this->assertNull($service->invoices->first()->period_end);
        $this->assertSame(66000000, $service->invoices->first()->total_amount);
        $this->plan->update(['quarterly_price' => 90000000, 'traffic_price_per_gb' => 50000]);
        $this->assertSame(55000000, $service->fresh()->agreement['recurring_price']);
        $this->assertSame(10000, $service->fresh()->agreement['traffic_price_per_gb']);
        $this->get('https://cp.localhost/colocation/invoices/'.$service->invoices->first()->id)->assertOk()->assertSee('مبلغ صورتحساب')->assertDontSee('کیف پول شما خالی است');
    }

    public function test_stale_prices_and_disabled_intervals_require_a_fresh_checkout(): void
    {
        $payload = $this->checkoutData();
        $this->plan->update(['quarterly_price' => 65000000]);
        $this->actingAs($this->customer, 'customer')->post('https://cp.localhost/colocation/plans/'.$this->plan->uuid, $payload)->assertSessionHasErrors('plan_version');
        $this->plan->update(['quarterly_price' => null]);
        $payload['plan_version'] = hash('sha256', $this->plan->fresh()->toJson());
        $this->post('https://cp.localhost/colocation/plans/'.$this->plan->uuid, $payload)->assertSessionHasErrors('billing_interval');
        $this->assertDatabaseCount('colocation_services', 0);
    }

    public function test_successful_payment_is_applied_once_without_charging_the_wallet_or_starting_billing(): void
    {
        $service = $this->order();
        $invoice = $service->invoices()->firstOrFail();
        $this->customer->wallet()->update(['balance' => -1000000, 'is_locked' => true]);
        $this->actingAs($this->customer, 'customer')->post('https://cp.localhost/colocation/invoices/'.$invoice->id.'/pay', ['gateway' => 'mellat'])->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->assertSame(Payment::TYPE_INVOICE, $payment->type);
        $this->get('https://cp.localhost/wallet/payments/'.$payment->id.'/gateway')->assertOk()->assertSee('مستقیماً صورتحساب هم‌مکانی')->assertDontSee('کیف پول شما به مبلغ پرداخت شده شارژ می‌شود');
        $this->complete($payment);
        $this->complete($payment->fresh());
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('awaiting_installation', $service->fresh()->status);
        $this->assertNull($service->fresh()->billing_starts_at);
        $this->assertNull($service->fresh()->paid_through);
        $this->assertSame(-1000000, $this->customer->wallet->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->get('https://cp.localhost/colocation/invoices/'.$invoice->id)->assertOk()->assertSee('پرداخت تأیید شد')->assertSee($payment->receiptNumber());
        $this->get('https://cp.localhost/colocation/services/'.$service->uuid)->assertOk()->assertSee('مراحل نصب شما');
        $this->assertSame(0, app(ColocationBillingService::class)->generateRenewals());
    }

    public function test_duplicate_pay_clicks_reuse_the_pending_gateway_attempt(): void
    {
        $invoice = $this->order()->invoices()->firstOrFail();
        $billing = app(ColocationBillingService::class);
        $first = $billing->pay($invoice, 'mellat');
        $second = $billing->pay($invoice, 'mellat');
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_gateway_callback_returns_to_the_invoice_and_repeated_callbacks_are_safe(): void
    {
        $service = $this->order();
        $invoice = $service->invoices()->firstOrFail();
        $payment = app(ColocationBillingService::class)->pay($invoice, 'mellat');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post('https://cp.localhost/wallet/payments/'.$payment->id.'/callback', $this->gatewayCallback($payment))->assertRedirect('https://cp.localhost/colocation/invoices/'.$invoice->id);
        }
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0, $this->customer->wallet->fresh()->balance);
        $this->assertSame(1, $service->events()->where('kind', 'paid')->count());
    }

    public function test_failed_payment_can_be_retried_without_starting_installation(): void
    {
        $service = $this->order();
        $invoice = $service->invoices()->firstOrFail();
        $payment = app(ColocationBillingService::class)->pay($invoice, 'mellat');
        $this->post('https://cp.localhost/wallet/payments/'.$payment->id.'/callback', ['ResCode' => '17'])->assertRedirect();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertSame('awaiting_payment', $service->fresh()->status);
        $retry = app(ColocationBillingService::class)->pay($invoice, 'mellat');
        $this->assertNotSame($payment->id, $retry->id);
    }

    public function test_admin_tracks_installation_and_starts_the_full_prepaid_period_on_the_chosen_date(): void
    {
        $service = $this->paid();
        $this->actingAs($this->admin, 'admin');
        $this->patch('https://admin.localhost/colocation/services/'.$service->uuid.'/progress', ['status' => 'testing', 'message' => 'Equipment installed and tested.', 'equipment' => 'Dell / SERIAL-123 / Rack A'])->assertRedirect();
        $this->assertSame('testing', $service->fresh()->status);
        $this->assertNull($service->fresh()->billing_starts_at);
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'Asia/Tehran'));
        $this->post('https://admin.localhost/colocation/services/'.$service->uuid.'/activate', ['billing_starts_at' => '2026-11-10', 'confirm_activation' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $service->refresh();
        $this->assertSame('2026-11-10', $service->billing_starts_at->setTimezone('Asia/Tehran')->toDateString());
        $this->assertSame('2027-02-10', $service->paid_through->setTimezone('Asia/Tehran')->toDateString());
        $this->assertFalse($service->invoices()->first()->meta['coverage_pending']);
        $this->post('https://admin.localhost/colocation/services/'.$service->uuid.'/activate', ['billing_starts_at' => '2026-11-11', 'confirm_activation' => '1'])->assertStatus(409);
        $this->actingAs($this->customer, 'customer')->get('https://cp.localhost/colocation/services/'.$service->uuid)->assertOk()->assertSee('SERIAL-123')->assertSee('Equipment installed and tested.');
    }

    public function test_unpaid_orders_cannot_be_activated_and_backdating_requires_a_reason(): void
    {
        $service = $this->order();
        $this->actingAs($this->admin, 'admin')->post('https://admin.localhost/colocation/services/'.$service->uuid.'/activate', ['billing_starts_at' => '2026-11-01', 'confirm_activation' => '1'])->assertStatus(409);
        $service = $this->paid();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'Asia/Tehran'));
        $this->post('https://admin.localhost/colocation/services/'.$service->uuid.'/activate', ['billing_starts_at' => '2026-11-05', 'confirm_activation' => '1'])->assertSessionHasErrors('reason');
        $this->post('https://admin.localhost/colocation/services/'.$service->uuid.'/activate', ['billing_starts_at' => '2026-11-05', 'reason' => 'Agreed reservation date', 'confirm_activation' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('active', $service->fresh()->status);
    }

    public function test_monthly_quarterly_and_yearly_renewals_use_the_original_anchor_and_are_idempotent(): void
    {
        foreach (['monthly' => '2026-12-01', 'quarterly' => '2027-02-01', 'yearly' => '2027-11-01'] as $interval => $expected) {
            $service = $this->paid($interval);
            app(ColocationBillingService::class)->activate($service, now('Asia/Tehran')->startOfDay()->toImmutable(), '', $this->admin->id);
            $this->assertSame($expected, $service->fresh()->paid_through->setTimezone('Asia/Tehran')->toDateString());
        }
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:00:00', 'Asia/Tehran'));
        $billing = app(ColocationBillingService::class);
        $this->assertSame(1, $billing->generateRenewals());
        $this->assertSame(0, $billing->generateRenewals());
        $renewal = Invoice::where('colocation_key', 'like', '%:renewal:1')->firstOrFail();
        $this->assertSame(22000000, $renewal->total_amount);
        $this->assertSame('2026-12-01', $renewal->due_at->setTimezone('Asia/Tehran')->toDateString());
        $this->assertSame('issued', $renewal->status);
        $this->assertSame('2026-12-01', $renewal->colocationService->paid_through->setTimezone('Asia/Tehran')->toDateString());
        $this->complete($billing->pay($renewal, 'mellat'));
        $this->assertSame('2027-01-01', $renewal->colocationService->fresh()->paid_through->setTimezone('Asia/Tehran')->toDateString());
    }

    public function test_end_of_month_boundaries_do_not_drift_after_short_months(): void
    {
        $service = $this->paid('monthly');
        app(ColocationBillingService::class)->activate($service, CarbonImmutable::parse('2027-01-31', 'Asia/Tehran'), '', $this->admin->id);
        $service->refresh();
        $this->assertSame('2027-02-28', $service->boundary(1)->setTimezone('Asia/Tehran')->toDateString());
        $this->assertSame('2027-03-31', $service->boundary(2)->setTimezone('Asia/Tehran')->toDateString());
    }

    public function test_traffic_is_monthly_on_a_yearly_plan_and_uses_the_agreed_rate_once(): void
    {
        $service = $this->paid('yearly');
        $billing = app(ColocationBillingService::class);
        $billing->activate($service, now('Asia/Tehran')->startOfDay()->toImmutable(), '', $this->admin->id);
        $this->travelTo(CarbonImmutable::parse('2026-12-02', 'Asia/Tehran'));
        $invoice = $billing->traffic($service, 0, 1250500, 'Switch counters, reviewed', $this->admin->id);
        $this->assertSame(2755500, $invoice->total_amount);
        $this->assertSame(2505000, $invoice->subtotal_amount);
        $this->assertSame($invoice->id, $billing->traffic($service, 0, 9000000, 'Duplicate', $this->admin->id)->id);
        $this->assertSame(1250500, $invoice->fresh()->meta['usage_mb']);
        $this->assertSame('2027-11-01', $service->fresh()->paid_through->setTimezone('Asia/Tehran')->toDateString());
        $this->actingAs($this->customer, 'customer')->get('https://cp.localhost/colocation/invoices/'.$invoice->id)->assertOk()->assertSee('Switch counters, reviewed')->assertSee('1,250.500');
    }

    public function test_usage_within_the_allowance_is_zero_and_does_not_request_payment(): void
    {
        $service = $this->paid();
        $billing = app(ColocationBillingService::class);
        $billing->activate($service, now('Asia/Tehran')->startOfDay()->toImmutable(), '', $this->admin->id);
        $this->travelTo(CarbonImmutable::parse('2026-12-02', 'Asia/Tehran'));
        $invoice = $billing->traffic($service, 0, 500000, 'Reviewed switch totals', $this->admin->id);
        $this->assertSame(0, $invoice->total_amount);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_another_customer_and_workspace_viewers_cannot_access_prices_services_or_payments(): void
    {
        $service = $this->paid();
        $invoice = $service->invoices()->firstOrFail();
        $outsider = Customer::factory()->create();
        $this->actingAs($outsider, 'customer')->get('https://cp.localhost/colocation/services/'.$service->uuid)->assertNotFound();
        $this->get('https://cp.localhost/colocation/invoices/'.$invoice->id)->assertNotFound();
        $this->get('https://cp.localhost/wallet/payments/'.Payment::firstOrFail()->id.'/gateway')->assertNotFound();
        $this->project->members()->create(['customer_id' => $outsider->id, 'role' => ProjectMember::ROLE_VIEWER]);
        $this->withSession([ProjectAccessService::SESSION_KEY => $this->project->id])->get('https://cp.localhost/colocation')->assertNotFound();
        $this->post('https://cp.localhost/colocation/services/'.$service->uuid.'/cancel', ['reason' => 'No access'])->assertNotFound();
    }

    public function test_disabling_new_purchases_preserves_existing_service_and_invoice_access(): void
    {
        $service = $this->paid();
        $this->customer->forceFill(['colocation_enabled' => false])->save();
        $this->actingAs($this->customer, 'customer')->get('https://cp.localhost/colocation')->assertOk()->assertSee($service->name);
        $this->get('https://cp.localhost/colocation/plans/'.$this->plan->uuid)->assertNotFound();
        $this->get('https://cp.localhost/colocation/invoices')->assertOk();
    }

    public function test_admin_can_define_private_plans_and_enable_customer_access(): void
    {
        $other = Customer::factory()->create();
        $this->actingAs($this->admin, 'admin')->patch('https://admin.localhost/colocation/customers/'.$other->id.'/access', ['enabled' => '1'])->assertSessionHasErrors('enabled');
        $this->post('https://admin.localhost/colocation/customers/'.$other->id.'/plans', [
            'name' => 'Agreed 4U', 'location' => 'Tehran', 'rack_units' => 4, 'power' => '500 W', 'connectivity' => '1 Gbps',
            'traffic_policy' => 'unmetered', 'included_traffic_gb' => 0, 'traffic_price_per_gb_toman' => 0,
            'monthly_price_toman' => 5000000, 'quarterly_price_toman' => '', 'yearly_price_toman' => 50000000,
            'setup_fee_toman' => 1000000, 'tax_rate' => 10, 'terms' => 'Agreed terms', 'available' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(50000000, $other->colocationPlans()->firstOrFail()->monthly_price);
        $this->patch('https://admin.localhost/colocation/customers/'.$other->id.'/access', ['enabled' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue((bool) $other->fresh()->colocation_enabled);
        $this->get('https://admin.localhost/colocation')->assertOk()->assertSee('هم‌مکانی و صف نصب');
        $this->get('https://admin.localhost/colocation/customers/'.$other->id)->assertOk()->assertSee('Agreed 4U');
        $service = $this->paid();
        $this->get('https://admin.localhost/colocation/services/'.$service->uuid)->assertOk()->assertSee('تکمیل نصب و شروع صورتحساب');
    }

    public function test_read_only_accountants_cannot_activate_services_or_change_prices(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $service = $this->paid();
        $this->actingAs($accountant, 'admin')->get('https://admin.localhost/colocation')->assertOk();
        $this->post('https://admin.localhost/colocation/services/'.$service->uuid.'/activate', ['billing_starts_at' => '2026-11-01', 'confirm_activation' => '1'])->assertForbidden();
        $this->patch('https://admin.localhost/colocation/customers/'.$this->customer->id.'/access', ['enabled' => '0'])->assertForbidden();
    }

    public function test_cancellation_stops_future_renewals_without_affecting_the_wallet(): void
    {
        $service = $this->paid('monthly');
        $billing = app(ColocationBillingService::class);
        $billing->activate($service, now('Asia/Tehran')->startOfDay()->toImmutable(), '', $this->admin->id);
        $this->actingAs($this->customer, 'customer')->post('https://cp.localhost/colocation/services/'.$service->uuid.'/cancel', ['reason' => 'Collect equipment at period end'])->assertRedirect();
        $this->assertNotNull($service->fresh()->cancellation_requested_at);
        $billing->end($service, $service->fresh()->paid_through, 'Collection agreed at period end.', $this->admin->id);
        $this->travelTo(CarbonImmutable::parse('2026-11-20', 'Asia/Tehran'));
        $this->assertSame(0, $billing->generateRenewals());
        $this->travelTo(CarbonImmutable::parse('2026-12-02', 'Asia/Tehran'));
        $billing->generateRenewals();
        $this->assertSame('cancelled', $service->fresh()->status);
        $this->assertSame(0, $this->customer->wallet->fresh()->balance);
    }

    public function test_reminders_are_sent_once_per_invoice_stage_and_billing_command_is_available(): void
    {
        $service = $this->order();
        $this->travel(5)->days();
        $billing = app(ColocationBillingService::class);
        $this->assertSame(1, $billing->sendReminders());
        $this->assertSame(0, $billing->sendReminders());
        $this->travel(3)->days();
        $this->assertSame(1, $billing->sendReminders());
        $this->assertSame(0, $billing->sendReminders());
        $this->artisan('colocation:bill')->assertSuccessful();
        $this->assertSame(2, $service->events()->where('kind', 'like', 'reminder:%')->count());
    }

    public function test_tehran_start_dates_are_preserved_when_the_application_stores_utc(): void
    {
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');
        $service = $this->paid('monthly');
        app(ColocationBillingService::class)->activate($service, CarbonImmutable::parse('2026-11-10', 'Asia/Tehran'), '', $this->admin->id);
        $service->refresh();
        $this->assertSame('2026-11-10 00:00', $service->billing_starts_at->setTimezone('Asia/Tehran')->format('Y-m-d H:i'));
        $this->assertSame('2026-12-10 00:00', $service->paid_through->setTimezone('Asia/Tehran')->format('Y-m-d H:i'));
    }

    public function test_paying_later_periods_does_not_hide_an_unpaid_gap(): void
    {
        $service = $this->paid('monthly');
        $billing = app(ColocationBillingService::class);
        $billing->activate($service, now('Asia/Tehran')->startOfDay()->toImmutable(), '', $this->admin->id);
        $this->travelTo(CarbonImmutable::parse('2027-01-20', 'Asia/Tehran'));
        $this->assertSame(3, $billing->generateRenewals());
        $later = $service->invoices()->where('colocation_key', $service->uuid.':renewal:2')->firstOrFail();
        $this->complete($billing->pay($later, 'mellat'));
        $this->assertSame('2026-12-01', $service->fresh()->paid_through->setTimezone('Asia/Tehran')->toDateString());
        $earlier = $service->invoices()->where('colocation_key', $service->uuid.':renewal:1')->firstOrFail();
        $this->complete($billing->pay($earlier, 'mellat'));
        $this->assertSame('2027-02-01', $service->fresh()->paid_through->setTimezone('Asia/Tehran')->toDateString());
    }

    public function test_metered_traffic_has_no_allowance_and_unfinished_months_are_rejected(): void
    {
        $this->plan->update(['traffic_policy' => 'metered', 'included_traffic_gb' => 0]);
        $service = $this->paid();
        $billing = app(ColocationBillingService::class);
        $billing->activate($service, now('Asia/Tehran')->startOfDay()->toImmutable(), '', $this->admin->id);
        $this->actingAs($this->admin, 'admin')->post('https://admin.localhost/colocation/services/'.$service->uuid.'/traffic', ['month' => 0, 'usage_gb' => '100.125', 'source' => 'Reviewed source', 'confirm_traffic' => '1'])->assertStatus(422);
        $this->travelTo(CarbonImmutable::parse('2026-12-02', 'Asia/Tehran'));
        $invoice = $billing->traffic($service, 0, 100125, 'Reviewed source', $this->admin->id);
        $this->assertSame(1001250, $invoice->subtotal_amount);
        $this->assertSame(0, $invoice->meta['included_mb']);
    }

    public function test_unmetered_plans_cannot_generate_metered_traffic_invoices(): void
    {
        $this->plan->update(['traffic_policy' => 'unmetered', 'traffic_price_per_gb' => 0]);
        $service = $this->paid();
        app(ColocationBillingService::class)->activate($service, now('Asia/Tehran')->startOfDay()->toImmutable(), '', $this->admin->id);
        $this->travelTo(CarbonImmutable::parse('2026-12-02', 'Asia/Tehran'));
        $this->actingAs($this->admin, 'admin')->post('https://admin.localhost/colocation/services/'.$service->uuid.'/traffic', ['month' => 0, 'usage_gb' => '100', 'source' => 'Source', 'confirm_traffic' => '1'])->assertStatus(409);
        $this->assertSame(1, $service->invoices()->count());
    }

    public function test_checkout_rejects_a_workspace_changed_in_another_tab(): void
    {
        $payload = $this->checkoutData();
        $anotherProject = $this->customer->ownedProjects()->create(['name' => 'Other workspace']);
        $anotherProject->members()->create(['customer_id' => $this->customer->id, 'role' => 'owner']);
        $this->actingAs($this->customer, 'customer')->withSession([ProjectAccessService::SESSION_KEY => $anotherProject->id])->post('https://cp.localhost/colocation/plans/'.$this->plan->uuid, $payload)->assertSessionHasErrors('workspace');
        $this->assertDatabaseCount('colocation_services', 0);
    }

    public function test_cancellation_voids_an_unpaid_renewal_and_blocks_payment_of_it(): void
    {
        $service = $this->paid('monthly');
        $billing = app(ColocationBillingService::class);
        $billing->activate($service, now('Asia/Tehran')->startOfDay()->toImmutable(), '', $this->admin->id);
        $this->travelTo(CarbonImmutable::parse('2026-11-20', 'Asia/Tehran'));
        $billing->generateRenewals();
        $renewal = $service->invoices()->where('status', 'issued')->firstOrFail();
        $billing->end($service, $service->fresh()->paid_through, 'Collection confirmed.', $this->admin->id);
        $this->assertSame('void', $renewal->fresh()->status);
        $this->actingAs($this->customer, 'customer')->post('https://cp.localhost/colocation/invoices/'.$renewal->id.'/pay', ['gateway' => 'mellat'])->assertStatus(409);
    }

    public function test_cancellation_cannot_void_a_renewal_with_a_pending_bank_payment(): void
    {
        $service = $this->paid('monthly');
        $billing = app(ColocationBillingService::class);
        $billing->activate($service, now('Asia/Tehran')->startOfDay()->toImmutable(), '', $this->admin->id);
        $this->travelTo(CarbonImmutable::parse('2026-11-20', 'Asia/Tehran'));
        $billing->generateRenewals();
        $renewal = $service->invoices()->where('status', 'issued')->firstOrFail();
        $billing->pay($renewal, 'mellat');
        $this->actingAs($this->admin, 'admin')->post('https://admin.localhost/colocation/services/'.$service->uuid.'/end', ['ends_at' => $service->fresh()->paid_through->toIso8601String(), 'message' => 'Collection confirmed', 'confirm_end' => '1'])->assertSessionHasErrors('ends_at');
        $this->assertSame('issued', $renewal->fresh()->status);
        $this->assertNull($service->fresh()->ends_at);
    }

    public function test_generic_financial_links_use_the_colocation_experience(): void
    {
        $service = $this->paid();
        $invoice = $service->invoices()->firstOrFail();
        $payment = $invoice->payments()->firstOrFail();
        $this->actingAs($this->customer, 'customer')->get('https://cp.localhost/invoices/'.$invoice->id)->assertRedirect('/colocation/invoices/'.$invoice->id);
        $this->get('https://cp.localhost/payments/'.$payment->id.'/receipt')->assertRedirect('/colocation/invoices/'.$invoice->id);
        $this->actingAs($this->admin, 'admin')->get('https://admin.localhost/billing/payments/'.$payment->id)->assertOk()->assertSee('پرداخت مستقیم صورتحساب هم‌مکانی')->assertDontSee('ارسال به حسابرو');
        $this->get('https://admin.localhost/billing/invoices/'.$invoice->id)->assertOk()->assertSee('پس از نصب تعیین می‌شود');
    }

    public function test_service_history_prevents_customer_and_workspace_deletion(): void
    {
        $this->order();
        $blockers = app(ProjectLifecycleService::class)->deletionBlockers($this->project, $this->customer);
        $this->assertArrayHasKey('colocation', $blockers);
        $this->actingAs($this->admin, 'admin')->delete('https://admin.localhost/customers/'.$this->customer->id)->assertSessionHasErrors('customer');
        $this->assertNotNull($this->customer->fresh());
    }

    private function checkoutData(): array
    {
        return ['workspace' => $this->project->uuid, 'name' => 'Office equipment', 'billing_interval' => 'quarterly', 'checkout_key' => (string) Str::uuid(), 'plan_version' => hash('sha256', $this->plan->fresh()->toJson()), 'accept_terms' => '1'];
    }

    private function order(string $interval = 'quarterly'): ColocationService
    {
        return app(ColocationBillingService::class)->order($this->project, $this->plan, $interval, 'Office equipment', (string) Str::uuid());
    }

    private function paid(string $interval = 'quarterly'): ColocationService
    {
        $service = $this->order($interval);
        $this->complete(app(ColocationBillingService::class)->pay($service->invoices()->firstOrFail(), 'mellat'));

        return $service->refresh();
    }

    private function complete(Payment $payment): void
    {
        app(PaymentService::class)->completeTopUp($payment, $this->gatewayCallback($payment));
    }

    private function gatewayCallback(Payment $payment): array
    {
        return ['RefId' => $payment->authority, 'ResCode' => '0', 'SaleOrderId' => $payment->id, 'SaleReferenceId' => '12792698'.$payment->id];
    }
}
