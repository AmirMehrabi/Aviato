<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\DomainContact;
use App\Models\DomainOperation;
use App\Models\DomainOrder;
use App\Models\DomainProduct;
use App\Models\ManagedDomain;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Domains\DomainFulfillmentService;
use App\Services\Domains\DomainLifecycleService;
use App\Services\Domains\DomainManagementService;
use App\Services\Domains\DomainOrderService;
use App\Services\Domains\DomainPricingService;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DomainCommerceTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 25)->setTime(10, 0));
        Cache::flush();
        Queue::fake();
        Http::preventStrayRequests();
        AppSetting::setValue(AppSetting::DOMAIN_RESELLER_TOKEN, 'test-secret', 'string', 'domains');
        AppSetting::setValue(AppSetting::DOMAIN_MANAGEMENT_ENABLED, true, 'boolean', 'domains');
        AppSetting::setValue('domains.sales_enabled', true, 'boolean', 'domains');
        AppSetting::setValue(AppSetting::BILLING_CURRENCY, 'IRR', 'string', 'billing');
        AppSetting::setValue(AppSetting::TAX_ENABLED, false, 'boolean', 'billing');
        $this->customer = Customer::factory()->create();
        $this->customer->forceFill(['domain_management_enabled' => true])->save();
        foreach (['register', 'renew'] as $operation) {
            DomainProduct::create(['extension' => 'com', 'operation' => $operation, 'period' => 1, 'enabled' => true, 'supplier_price' => 8000000, 'retail_price' => 10000000, 'synced_at' => now()]);
        }
        $this->fakeProvider();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function fakeProvider(string $state = 'done', bool $registrationTimeout = false, int $cost = 8000000, string $expiry = '2027-10-25'): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($state, $registrationTimeout, $cost, $expiry) {
            $url = $request->url();
            if (str_ends_with($url, '/check')) {
                return Http::response(['domain' => 'example.com', 'available' => true, 'premium' => false, 'price' => $cost]);
            }
            if (str_ends_with($url, '/final/prices')) {
                return Http::response(['com' => ['register' => [1 => ['price' => '9000000.00', 'promotion' => '8500000.00']], 'renew' => [1 => ['price' => 9000000]]], 'ir' => ['register' => [5 => ['price' => 500000]]]]);
            }
            if (str_ends_with($url, '/contact/create')) {
                return Http::response(['trackId' => 10, 'status' => 'pending']);
            }
            if (str_ends_with($url, '/contact/info')) {
                return Http::response(['contact' => 'CCOM-123', 'email' => 'owner@example.org']);
            }
            if (str_ends_with($url, '/domain/register')) {
                return $registrationTimeout ? Http::failedConnection() : Http::response(['trackId' => 20]);
            }
            if (str_ends_with($url, '/renew')) {
                return Http::response(['trackId' => 30]);
            }
            if (str_ends_with($url, '/dns')) {
                return Http::response(['trackId' => 40]);
            }
            if (str_contains($url, '/manage/inquiry/')) {
                $track = basename($url);

                return Http::response(['data' => [['trackId' => (int) $track, 'job' => match ($track) {
                    '10' => 'CreateContactJob', '30' => 'RenewJob', '40' => 'UpdateDnsJob', default => 'RegisterJob'
                }, 'item' => $track === '10' ? 'owner@example.org' : 'example.com', 'status' => $track === '10' ? 'done' : $state]]]);
            }
            if (str_ends_with($url, '/info')) {
                return Http::response(['id' => 123, 'domain' => 'example.com', 'expire_at' => $expiry, 'dns' => ['ns1.example.net', 'ns2.example.net']]);
            }
            throw new \RuntimeException('Unexpected HTTP request: '.$url);
        });
    }

    private function order(bool $fund = false): DomainOrder
    {
        $order = app(DomainOrderService::class)->create($this->customer, 'example.com', 1);
        $order->update(['registrant' => ['name' => 'Owner Name', 'email' => 'owner@example.org', 'country' => 'IR', 'state' => 'Tehran', 'city' => 'Tehran', 'address' => 'Test address', 'postCode' => '1234567890', 'phone' => '+98.9121234567', 'fax' => '+98.9121234567', 'organization' => 'Owner Name'], 'nameservers' => ['ns1.example.net', 'ns2.example.net'], 'consented_at' => now()]);
        if ($fund) {
            app(WalletService::class)->credit($this->customer, 15000000, 'Test deposit');
        }

        return $order->fresh();
    }

    private function owned(): ManagedDomain
    {
        $order = $this->order(true);
        app(DomainOrderService::class)->pay($order, null);
        app(DomainFulfillmentService::class)->process($order->fresh());

        return ManagedDomain::firstOrFail();
    }

    public function test_search_shows_retail_and_current_renewal_but_never_supplier_costs(): void
    {
        $this->actingAs($this->customer, 'customer')->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])
            ->assertOk()->assertJsonPath('data.retail.periods', [1])->assertJsonMissingPath('data.price')->assertJsonMissingPath('data.supplier_price')->assertJsonStructure(['data' => ['retail' => ['total', 'renewal']]])->assertDontSee('test-secret');
    }

    public function test_checkout_saves_steps_prefills_owner_and_returns_to_review(): void
    {
        $this->actingAs($this->customer, 'customer')->post('https://cp.localhost/domains/orders', ['domain' => 'example.com', 'period' => 1])->assertRedirect();
        $order = DomainOrder::firstOrFail();
        $this->get('https://cp.localhost/domains/orders/'.$order->uuid)->assertOk()->assertSee('دامنه به نام چه کسی ثبت شود؟')->assertSee($this->customer->email);
        $this->patch('https://cp.localhost/domains/orders/'.$order->uuid, ['stage' => 'owner', 'name' => 'Owner Name', 'email' => 'owner@example.org', 'country' => 'IR', 'state' => 'Tehran', 'city' => 'Tehran', 'address' => 'Test address', 'postCode' => '12345', 'phone' => '۰۹۱۲۱۲۳۴۵۶۷', 'save_contact' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('domain_contacts', 1);
        $this->assertSame('+98.9121234567', $order->fresh()->registrant['phone']);
        $this->assertStringNotContainsString('owner@example.org', DB::table('domain_contacts')->value('details'));
        $this->patch('https://cp.localhost/domains/orders/'.$order->uuid, ['stage' => 'setup', 'nameservers' => ['ns1.example.net', 'ns2.example.net']])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('https://cp.localhost/domains/orders/'.$order->uuid.'?step=review')->assertOk()->assertSee('همه‌چیز آماده است؟')->assertSee('owner@example.org');
    }

    public function test_saved_contact_profiles_are_scoped_to_customer(): void
    {
        $order = $this->order();
        $foreign = DomainContact::create(['customer_id' => Customer::factory()->create()->id, 'label' => 'Private', 'details' => ['email' => 'private@example.org']]);
        $this->actingAs($this->customer, 'customer')->patch('https://cp.localhost/domains/orders/'.$order->uuid, ['stage' => 'owner', 'contact_id' => $foreign->id])->assertNotFound();
    }

    public function test_purchase_completes_once_with_one_charge_invoice_and_owned_domain(): void
    {
        $order = $this->order(true);
        $service = app(DomainOrderService::class);
        $service->pay($order, null);
        $service->pay($order->fresh(), null);
        $this->assertSame(5000000, $this->customer->wallet->fresh()->balance);
        $this->assertSame('funded', $order->fresh()->status);
        $this->assertDatabaseCount('domain_claims', 1);
        app(DomainFulfillmentService::class)->process($order->fresh());
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertDatabaseCount('managed_domains', 1);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('domain_claims', 0);
        $this->assertSame(1, WalletTransaction::where('type', 'charge')->count());
        $this->actingAs($this->customer, 'customer')->get('https://cp.localhost/domains/orders/'.$order->uuid)->assertOk()->assertSee('مدیریت دامنه');
        $this->get('https://cp.localhost/domains/orders/'.$order->uuid.'/invoice')->assertOk()->assertSee('DOM-00000001')->assertSee('ثبت دامنه example.com');
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/domain/register'))->count());
    }

    public function test_timeout_after_submission_keeps_money_reserved_and_never_registers_twice(): void
    {
        $order = $this->order(true);
        app(DomainOrderService::class)->pay($order, null);
        $this->fakeProvider(registrationTimeout: true);
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame('needs_review', $order->fresh()->status);
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame(5000000, $this->customer->wallet->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('type', 'refund')->count());
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/domain/register'));
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/domain/register'))->count());
    }

    public function test_confirmed_failure_releases_reservation_exactly_once_even_for_locked_wallet(): void
    {
        $order = $this->order(true);
        app(DomainOrderService::class)->pay($order, null);
        $this->customer->wallet->update(['is_locked' => true]);
        $this->fakeProvider(state: 'failed');
        app(DomainFulfillmentService::class)->process($order->fresh());
        app(DomainOrderService::class)->fail($order->fresh(), 'Failure');
        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame(15000000, $this->customer->wallet->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('type', 'refund')->count());
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('domain_claims', 0);
    }

    public function test_expired_quote_cannot_charge_until_explicitly_refreshed_and_accepted(): void
    {
        $order = $this->order(true);
        $this->travel(16)->minutes();
        $this->actingAs($this->customer, 'customer')->post('https://cp.localhost/domains/orders/'.$order->uuid.'/pay', ['terms' => 1])->assertSessionHasErrors('payment');
        $this->assertSame(15000000, $this->customer->wallet->fresh()->balance);
        $this->post('https://cp.localhost/domains/orders/'.$order->uuid.'/refresh')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($order->fresh()->consented_at);
    }

    public function test_premium_or_increased_supplier_cost_does_not_charge_customer(): void
    {
        $order = $this->order(true);
        $this->fakeProvider(cost: 11000000);
        try {
            app(DomainOrderService::class)->pay($order, null);
            $this->fail('Expected changed-price validation');
        } catch (ValidationException) {
        }
        $this->assertSame(15000000, $this->customer->wallet->fresh()->balance);
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['domain' => 'example.com', 'available' => true, 'premium' => true, 'price' => 8000000])]);
        $this->expectException(ValidationException::class);
        app(DomainOrderService::class)->pay($order, null);
    }

    public function test_two_orders_cannot_reserve_for_the_same_domain(): void
    {
        $order = $this->order(true);
        $second = $order->replicate();
        $second->uuid = (string) Str::uuid();
        $second->save();
        app(DomainOrderService::class)->pay($order, null);
        app(DomainOrderService::class)->pay($second, null);
        $this->assertSame(5000000, $this->customer->wallet->fresh()->balance);
        $this->assertNull($second->fresh()->wallet_transaction_id);
        $this->assertDatabaseCount('domain_claims', 1);
    }

    public function test_owner_keeps_management_and_renewal_when_new_sales_access_is_revoked(): void
    {
        $domain = $this->owned();
        $this->customer->forceFill(['domain_management_enabled' => false])->save();
        AppSetting::setValue(AppSetting::DOMAIN_MANAGEMENT_ENABLED, false, 'boolean', 'domains');
        AppSetting::setValue('domains.sales_enabled', false, 'boolean', 'domains');
        $this->actingAs($this->customer, 'customer')->get('https://cp.localhost/domains')->assertOk()->assertSee('دامنه‌های من');
        $this->get('https://cp.localhost/domains/'.$domain->uuid)->assertOk();
        $this->post('https://cp.localhost/domains/'.$domain->uuid.'/renew', ['period' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('https://cp.localhost/domains/orders', ['domain' => 'other.com', 'period' => 1])->assertForbidden();
        $this->postJson('https://cp.localhost/domains/check', ['domain' => 'other.com'])->assertForbidden();
    }

    public function test_other_customers_cannot_view_or_modify_orders_domains_or_invoices(): void
    {
        $domain = $this->owned();
        $order = DomainOrder::firstOrFail();
        $other = Customer::factory()->create();
        $other->forceFill(['domain_management_enabled' => true])->save();
        $this->actingAs($other, 'customer')->get('https://cp.localhost/domains/orders/'.$order->uuid)->assertNotFound();
        $this->post('https://cp.localhost/domains/orders/'.$order->uuid.'/pay', ['terms' => 1])->assertNotFound();
        $this->get('https://cp.localhost/domains/orders/'.$order->uuid.'/invoice')->assertNotFound();
        $this->get('https://cp.localhost/invoices/'.$order->invoice_id)->assertNotFound();
        $this->get('https://cp.localhost/domains/'.$domain->uuid)->assertNotFound();
        $this->patch('https://cp.localhost/domains/'.$domain->uuid.'/nameservers', ['nameservers' => ['ns1.other.net', 'ns2.other.net']])->assertNotFound();
    }

    public function test_price_sync_leaves_new_extensions_disabled_and_supports_ir_handle_mode(): void
    {
        $count = app(DomainPricingService::class)->sync();
        $this->assertSame(3, $count);
        $ir = DomainProduct::where('extension', 'ir')->firstOrFail();
        $this->assertFalse($ir->enabled);
        $this->assertSame('registry_handle', $ir->contact_mode);
        $this->assertSame(8500000, DomainProduct::where('extension', 'com')->where('operation', 'register')->value('supplier_price'));
        $this->actingAs(User::factory()->create(['role' => 'support']), 'admin')->get('https://admin.localhost/settings/domains/commerce')->assertForbidden();
        $this->actingAs(User::factory()->create(), 'admin')->get('https://admin.localhost/settings/domains/commerce')->assertOk()->assertSee('قیمت‌گذاری پسوندها');
    }

    public function test_stale_costs_or_missing_renewal_price_prevent_checkout(): void
    {
        DomainProduct::where('operation', 'register')->update(['synced_at' => now()->subDays(2)]);
        $this->actingAs($this->customer, 'customer')->post('https://cp.localhost/domains/orders', ['domain' => 'example.com', 'period' => 1])->assertSessionHasErrors('domain');
        DomainProduct::where('operation', 'register')->update(['synced_at' => now()]);
        DomainProduct::where('operation', 'renew')->update(['enabled' => false]);
        $this->post('https://cp.localhost/domains/orders', ['domain' => 'example.com', 'period' => 1])->assertSessionHasErrors('domain');
        $this->assertDatabaseCount('domain_orders', 0);
    }

    public function test_nameserver_changes_are_tracked_and_only_display_after_confirmation(): void
    {
        $domain = $this->owned();
        app(DomainManagementService::class)->nameservers($domain, ['ns1.new.net', 'ns2.new.net']);
        $this->assertSame(['ns1.example.net', 'ns2.example.net'], $domain->fresh()->nameservers);
        $operation = DomainOperation::where('kind', 'nameservers')->firstOrFail();
        app(DomainManagementService::class)->reconcile($operation);
        $this->assertSame(['ns1.new.net', 'ns2.new.net'], $domain->fresh()->nameservers);
        $this->assertSame('done', $operation->fresh()->status);
    }

    public function test_automatic_renewal_requires_consent_and_price_limit_and_disable_keeps_domain(): void
    {
        $domain = $this->owned();
        $this->actingAs($this->customer, 'customer')->patch('https://cp.localhost/domains/'.$domain->uuid.'/auto-renew', ['enabled' => 1, 'limit_toman' => 1500000])->assertSessionHasErrors('consent');
        $this->patch('https://cp.localhost/domains/'.$domain->uuid.'/auto-renew', ['enabled' => 1, 'limit_toman' => 1500000, 'consent' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($domain->fresh()->auto_renew);
        $this->assertSame(15000000, $domain->fresh()->auto_renew_limit);
        $this->patch('https://cp.localhost/domains/'.$domain->uuid.'/auto-renew', ['enabled' => 0])->assertRedirect();
        $this->assertFalse($domain->fresh()->auto_renew);
        $this->assertSame('active', $domain->fresh()->status);
    }

    public function test_renewal_needs_confirmed_extended_expiry_and_has_a_separate_invoice(): void
    {
        $domain = $this->owned();
        $domain->update(['expires_at' => now()->addDays(5)]);
        app(WalletService::class)->credit($this->customer, 15000000, 'Renewal funds');
        $this->fakeProvider(expiry: '2027-10-30');
        $order = app(DomainOrderService::class)->create($this->customer, $domain->name, 1, $domain);
        $order->update(['consented_at' => now()]);
        app(DomainOrderService::class)->pay($order->fresh(), null);
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertDatabaseCount('managed_domains', 1);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertSame('2027-10-30', $domain->fresh()->expires_at->toDateString());
    }

    public function test_gateway_payment_is_attached_to_order_before_initiation_and_callback_reserves_once(): void
    {
        $gateway = \Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('label')->andReturn('Test gateway');
        $gateway->shouldReceive('initiate')->once()->withArgs(fn (Payment $payment) => $payment->domain_order_id !== null)->andReturn(['authority' => 'test-authority']);
        $gateway->shouldReceive('complete')->once()->andReturn(['provider_reference' => 'test-ref']);
        $manager = \Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('available')->andReturn(['test' => 'Test gateway']);
        $manager->shouldReceive('gateway')->with('test')->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);
        $order = $this->order();
        app(WalletService::class)->credit($this->customer, 4000000, 'Partial funds');
        $payment = app(DomainOrderService::class)->pay($order, 'test');
        $this->assertSame(6000000, $payment->amount);
        $this->assertSame($order->id, $payment->domain_order_id);
        $payment->update(['gateway_payload' => $payment->gateway_payload + ['_aviato_domain_return_host' => 'my.aviato.ir']]);
        $payload = $payment->gateway_payload;
        $payload['_aviato_domain_return_host'] = 'my.aviato.ir';
        $payment->update(['gateway_payload' => $payload]);
        $this->get('https://cp.localhost/wallet/payments/'.$payment->id.'/callback')->assertRedirect('https://my.aviato.ir/domains/orders/'.$order->uuid.'?step=review');
        app(PaymentService::class)->completeTopUp($payment->fresh());
        $this->assertSame('funded', $order->fresh()->status);
        $this->assertSame(0, $this->customer->wallet->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('metadata->category', 'wallet_top_up')->count());
        $this->assertSame(1, WalletTransaction::where('metadata->category', 'domain_reservation')->count());
    }

    public function test_ir_checkout_uses_registry_handles_without_creating_international_contact(): void
    {
        foreach (['register', 'renew'] as $op) {
            DomainProduct::create(['extension' => 'ir', 'operation' => $op, 'period' => 1, 'enabled' => true, 'supplier_price' => 100000, 'retail_price' => 150000, 'synced_at' => now(), 'contact_mode' => 'registry_handle']);
        }
        $this->actingAs($this->customer, 'customer')->post('https://cp.localhost/domains/orders', ['domain' => 'example.ir', 'period' => 1])->assertRedirect();
        $order = DomainOrder::firstOrFail();
        $this->get('https://cp.localhost/domains/orders/'.$order->uuid)->assertOk()->assertSee('شناسه صاحب امتیاز')->assertDontSee('نام و نام خانوادگی مالک');
        $this->patch('https://cp.localhost/domains/orders/'.$order->uuid, ['stage' => 'owner', 'holder' => 'ab123-irnic', 'admin' => 'ab123-irnic', 'tech' => 'ab123-irnic', 'billing' => 'ab123-irnic'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('ab123-irnic', $order->fresh()->registrant['holder']);
        Http::assertNothingSent();
    }

    public function test_pending_registration_can_be_reconciled_without_resubmission(): void
    {
        $order = $this->order(true);
        app(DomainOrderService::class)->pay($order, null);
        $this->fakeProvider(state: 'pending');
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame('confirming', $order->fresh()->status);
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/domain/register'))->count());
        $this->fakeProvider();
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame('completed', $order->fresh()->status);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/domain/register') || str_ends_with($r->url(), '/contact/create'));
    }

    public function test_tax_is_snapshotted_and_invoice_does_not_add_it_twice(): void
    {
        AppSetting::setValue(AppSetting::TAX_ENABLED, true, 'boolean', 'billing');
        AppSetting::setValue(AppSetting::TAX_RATE_PERCENTAGE, 10, 'number', 'billing');
        $order = $this->order(true);
        $this->assertSame(11000000, $order->total);
        app(DomainOrderService::class)->pay($order, null);
        app(DomainFulfillmentService::class)->process($order->fresh());
        $invoice = $order->fresh()->invoice;
        $this->assertSame(10000000, $invoice->subtotal_amount);
        $this->assertSame(1000000, $invoice->tax_amount);
        $this->assertSame(11000000, $invoice->items()->sum('subtotal'));
        $this->assertSame(4000000, $this->customer->wallet->fresh()->balance);
    }

    public function test_automatic_renewal_reserves_once_within_limit_and_uses_no_gateway(): void
    {
        $domain = $this->owned();
        $domain->update(['expires_at' => now()->addDays(5), 'auto_renew' => true, 'auto_renew_limit' => 12000000, 'auto_renew_consented_at' => now()]);
        app(WalletService::class)->credit($this->customer, 10000000, 'Renewal funding');
        $this->fakeProvider(expiry: '2026-10-30');
        app(DomainLifecycleService::class)->daily();
        app(DomainLifecycleService::class)->daily();
        $this->assertSame(1, DomainOrder::where('operation', 'renew')->where('status', 'funded')->count());
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(5000000, $this->customer->wallet->fresh()->balance);
        $this->assertSame(1, $this->customer->notifications()->where('data->event', 'domain_expiry')->count());
    }

    public function test_automatic_renewal_respects_price_cap_and_low_funds_without_charging_bank(): void
    {
        $domain = $this->owned();
        $domain->update(['expires_at' => now()->addDays(5), 'auto_renew' => true, 'auto_renew_limit' => 9000000, 'auto_renew_consented_at' => now()]);
        $this->fakeProvider(expiry: '2026-10-30');
        app(DomainLifecycleService::class)->daily();
        $this->assertSame(0, DomainOrder::where('operation', 'renew')->where('status', 'funded')->count());
        $domain->update(['auto_renew_limit' => 12000000]);
        app(DomainLifecycleService::class)->daily();
        app(DomainLifecycleService::class)->daily();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(5000000, $this->customer->wallet->fresh()->balance);
        $this->assertSame(3, $this->customer->notifications()->where('data->event', 'domain_expiry')->count());
    }

    public function test_fixed_retail_survives_cost_sync_while_automatic_prices_respect_margin(): void
    {
        $product = DomainProduct::where('operation', 'register')->firstOrFail();
        $product->update(['minimum_margin' => 3000000]);
        app(DomainPricingService::class)->sync();
        $this->assertSame(10000000, $product->fresh()->retail_price);
        $this->actingAs($this->customer, 'customer')->post('https://cp.localhost/domains/orders', ['domain' => 'example.com', 'period' => 1])->assertSessionHasErrors('domain');
        $product->update(['automatic_pricing' => true, 'markup_percent' => 25]);
        app(DomainPricingService::class)->sync();
        $this->assertSame(11500000, $product->fresh()->retail_price);
    }

    public function test_funded_order_details_cannot_change_after_payment(): void
    {
        $order = $this->order(true);
        app(DomainOrderService::class)->pay($order, null);
        $this->actingAs($this->customer, 'customer')->patch('https://cp.localhost/domains/orders/'.$order->uuid, ['stage' => 'setup', 'nameservers' => ['ns1.other.net', 'ns2.other.net']])->assertStatus(409);
        $this->post('https://cp.localhost/domains/orders/'.$order->uuid.'/cancel')->assertStatus(409);
        $this->post('https://cp.localhost/domains/orders/'.$order->uuid.'/refresh')->assertStatus(409);
    }

    public function test_admin_cannot_attach_tracking_for_another_domain_and_must_record_refund_evidence(): void
    {
        $order = $this->order(true);
        app(DomainOrderService::class)->pay($order, null);
        $this->fakeProvider(registrationTimeout: true);
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->actingAs(User::factory()->create(), 'admin');
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['data' => [['trackId' => 999, 'item' => 'another.com', 'job' => 'RegisterJob', 'status' => 'done']]])]);
        $operation = $order->operations()->where('kind', 'register')->firstOrFail();
        $this->post('https://admin.localhost/settings/domains/orders/'.$order->uuid.'/reconcile', ['operation_id' => $operation->id, 'track_id' => '999'])->assertSessionHasErrors('track_id');
        $this->post('https://admin.localhost/settings/domains/orders/'.$order->uuid.'/not-submitted', ['confirmed_not_executed' => 1])->assertSessionHasErrors('provider_reference');
        $this->assertSame('needs_review', $order->fresh()->status);
        $this->post('https://admin.localhost/settings/domains/orders/'.$order->uuid.'/not-submitted', ['confirmed_not_executed' => 1, 'provider_reference' => 'Provider support ticket ABC123 confirmed not executed'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame(15000000, $this->customer->wallet->fresh()->balance);
    }

    public function test_irt_checkout_sends_rials_to_gateway_and_credits_wallet_in_tomans(): void
    {
        AppSetting::setValue(AppSetting::BILLING_CURRENCY, 'IRT', 'string', 'billing');
        $gateway = \Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('label')->andReturn('Test gateway');
        $gateway->shouldReceive('initiate')->once()->withArgs(fn (Payment $payment) => $payment->amount === 10000000 && $payment->currency === 'IRR')->andReturn(['authority' => 'irt-authority']);
        $gateway->shouldReceive('complete')->once()->andReturn(['provider_reference' => 'irt-ref']);
        $manager = \Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('available')->andReturn(['test' => 'Test gateway']);
        $manager->shouldReceive('gateway')->with('test')->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);
        $order = $this->order();
        $this->assertSame(1000000, $order->total);
        $payment = app(DomainOrderService::class)->pay($order, 'test');
        app(PaymentService::class)->completeTopUp($payment);
        $this->assertSame('funded', $order->fresh()->status);
        $this->assertSame(0, $this->customer->wallet->fresh()->balance);
        $this->assertSame(1000000, (int) WalletTransaction::where('metadata->category', 'wallet_top_up')->value('amount'));
    }

    public function test_late_payment_for_cancelled_order_only_credits_wallet(): void
    {
        $gateway = \Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('label')->andReturn('Test gateway');
        $gateway->shouldReceive('initiate')->once()->andReturn(['authority' => 'cancelled-authority']);
        $gateway->shouldReceive('complete')->once()->andReturn(['provider_reference' => 'cancelled-ref']);
        $manager = \Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('available')->andReturn(['test' => 'Test gateway']);
        $manager->shouldReceive('gateway')->with('test')->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);
        $order = $this->order();
        $payment = app(DomainOrderService::class)->pay($order, 'test');
        $this->actingAs($this->customer, 'customer')->post('https://cp.localhost/domains/orders/'.$order->uuid.'/cancel')->assertRedirect();
        app(PaymentService::class)->completeTopUp($payment);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10000000, $this->customer->wallet->fresh()->balance);
        $this->assertDatabaseCount('domain_claims', 0);
    }

    public function test_checkout_redirect_stays_on_customer_portal_alias(): void
    {
        $this->actingAs($this->customer, 'customer')->post('https://my.aviato.ir/domains/orders', ['domain' => 'example.com', 'period' => 1])->assertRedirect('https://my.aviato.ir/domains/orders/'.DomainOrder::firstOrFail()->uuid);
    }

    public function test_successful_job_without_full_purchased_term_does_not_settle_funds(): void
    {
        $order = $this->order(true);
        app(DomainOrderService::class)->pay($order, null);
        $this->fakeProvider(expiry: '2027-04-25');
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame('confirming', $order->fresh()->status);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('managed_domains', 0);
        $this->assertSame(5000000, $this->customer->wallet->fresh()->balance);
        $this->fakeProvider();
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame('completed', $order->fresh()->status);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/domain/register'));
    }

    public function test_malformed_owner_fields_return_validation_errors(): void
    {
        $order = $this->order();
        $this->actingAs($this->customer, 'customer')->patchJson('https://cp.localhost/domains/orders/'.$order->uuid, ['stage' => 'owner', 'country' => ['IR'], 'phone' => ['09121234567']])->assertUnprocessable()->assertJsonValidationErrors(['country', 'phone']);
    }
}
