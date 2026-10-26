<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\DomainOperation;
use App\Models\DomainOrder;
use App\Models\DomainProduct;
use App\Models\Invoice;
use App\Models\ManagedDomain;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Domains\DomainFulfillmentService;
use App\Services\Domains\DomainManagementService;
use App\Services\Domains\DomainOrderService;
use App\Services\Domains\DomainPricingService;
use App\Services\Domains\IrnicWhoisService;
use App\Services\Domains\IrpowerCatalogService;
use App\Services\Domains\IrpowerProvider;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class DomainProviderRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(10, 0));
        Cache::flush();
        Queue::fake();
        Http::preventStrayRequests();
        AppSetting::setValue(AppSetting::DOMAIN_RESELLER_TOKEN, 'world-test-key', 'string', 'domains');
        AppSetting::setValue(AppSetting::IRPOWER_API_KEY, 'irpower-test-key', 'string', 'domains');
        AppSetting::setValue('domains.irpower_enabled', true, 'boolean', 'domains');
        AppSetting::setValue('domains.sales_enabled', true, 'boolean', 'domains');
        AppSetting::setValue(AppSetting::DOMAIN_MANAGEMENT_ENABLED, true, 'boolean', 'domains');
        AppSetting::setValue(AppSetting::BILLING_CURRENCY, 'IRR', 'string', 'billing');
        AppSetting::setValue(AppSetting::TAX_ENABLED, false, 'boolean', 'billing');
        $this->customer = Customer::factory()->create();
        $this->customer->forceFill(['domain_management_enabled' => true])->save();
        $this->offer('reseller_world', 'register', 10000000);
        $this->offer('reseller_world', 'renew', 10000000);
        $this->offer('irpower', 'register', 9000000);
        $this->offer('irpower', 'renew', 15000000);
        $this->fakeProvider();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function offer(string $provider, string $operation, int $retail, string $extension = 'com'): DomainProduct
    {
        return DomainProduct::create(['provider' => $provider, 'extension' => $extension, 'operation' => $operation, 'period' => 1, 'enabled' => true,
            'supplier_price' => $retail - 1000000, 'retail_price' => $retail, 'synced_at' => now(), 'price_valid_until' => now()->addMonth(), 'contact_mode' => $extension === 'ir' ? 'registry_handle' : 'contact']);
    }

    private function fakeProvider(?string $failure = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($failure) {
            if ($request->url() === IrpowerProvider::URL) {
                if (in_array($request['command'], ['RegisterDomain', 'RenewDomain'], true)) {
                    return match ($failure) {
                        'timeout' => Http::failedConnection(),
                        'rejected' => Http::response(['success' => false, 'error' => ['code' => 6019, 'message' => 'Private provider details']]),
                        default => Http::response(['success' => 'true']),
                    };
                }

                return Http::response(['success' => true, 'result' => match ($request['command']) {
                    'GetContactInfo' => ['relations' => ['holder' => true, 'admin' => true, 'tech' => true, 'bill' => true]],
                    'GetDomainNameServers' => ['ns1' => 'ns1.example.net', 'ns2' => 'ns2.example.net'],
                    'GetDomainWhoisInfo' => ['registrant' => ['email' => 'owner@example.org']],
                    'UpdateDomainNameServers' => [],
                    default => throw new RuntimeException('Unexpected IRPOWER command'),
                }]);
            }
            if (str_ends_with($request->url(), '/check')) {
                return Http::response(['domain' => 'example.com', 'available' => true, 'premium' => false, 'price' => 9000000]);
            }
            throw new RuntimeException('Unexpected network request');
        });
    }

    private function order(bool $pay = false): DomainOrder
    {
        $order = app(DomainOrderService::class)->create($this->customer, 'example.com', 1);
        $order->update(['registrant' => ['name' => 'Owner Name', 'email' => 'owner@example.org', 'country' => 'IR', 'state' => 'Tehran', 'city' => 'Tehran', 'address' => 'Test address', 'postCode' => '1234567890', 'phone' => '+98.9121234567'],
            'nameservers' => ['ns1.example.net', 'ns2.example.net'], 'consented_at' => now()]);
        if ($pay) {
            app(WalletService::class)->credit($this->customer, 20000000, 'Fixture');
            app(DomainOrderService::class)->pay($order->fresh(), null);
        }

        return $order->fresh();
    }

    public function test_lowest_customer_price_is_chosen_and_renewal_comes_from_same_provider(): void
    {
        $pricing = app(DomainPricingService::class);
        $this->assertSame('irpower', $pricing->quote('example.com', 'register', 1)['provider']);
        $this->assertSame($pricing->renewalPrice('example.com', 'irpower'), $pricing->customerPrice('example.com')['renewal']);
        AppSetting::setValue('domains.routing_policy', 'two_year', 'string', 'domains');
        $this->assertSame('reseller_world', $pricing->quote('example.com', 'register', 1)['provider']);
        AppSetting::setValue('domains.provider_routes', ['com' => 'irpower'], 'json', 'domains');
        $this->assertSame('irpower', $pricing->quote('example.com', 'register', 1)['provider']);
    }

    public function test_expired_manual_cost_or_missing_renewal_excludes_offer(): void
    {
        DomainProduct::where('provider', 'irpower')->where('operation', 'renew')->update(['price_valid_until' => now()->subSecond()]);
        $this->assertSame('reseller_world', app(DomainPricingService::class)->quote('example.com', 'register', 1)['provider']);
        DomainProduct::where('provider', 'irpower')->where('operation', 'renew')->update(['price_valid_until' => now()->addMonth(), 'enabled' => false]);
        $this->assertSame('reseller_world', app(DomainPricingService::class)->quote('example.com', 'register', 1)['provider']);
    }

    public function test_order_stays_with_provider_after_routing_changes_and_old_other_provider_draft_is_not_reused(): void
    {
        AppSetting::setValue('domains.routing_policy', 'reseller_world', 'string', 'domains');
        $old = $this->order();
        AppSetting::setValue('domains.routing_policy', 'cheapest', 'string', 'domains');
        $new = $this->order();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('irpower', $new->provider);
        AppSetting::setValue('domains.routing_policy', 'reseller_world', 'string', 'domains');
        app(DomainOrderService::class)->refreshQuote($new);
        $this->assertSame('irpower', $new->fresh()->provider);
    }

    public function test_registration_submits_once_then_audited_confirmation_issues_one_invoice(): void
    {
        $order = $this->order(true);
        $fulfillment = app(DomainFulfillmentService::class);
        $fulfillment->process($order);
        $fulfillment->process($order->fresh());
        $this->assertSame('needs_review', $order->fresh()->status);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($pair) => $pair[0]->url() === IrpowerProvider::URL && $pair[0]['command'] === 'RegisterDomain')->count());
        $user = User::factory()->create();
        $this->actingAs($user, 'admin')->post('https://admin.localhost/settings/domains/orders/'.$order->uuid.'/irpower-confirm', ['expires_at' => '2027-10-26', 'provider_reference' => 'IRPOWER ticket confirmed 123456', 'verified' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame('irpower', ManagedDomain::firstOrFail()->provider);
        $this->assertSame('charge', WalletTransaction::findOrFail($order->fresh()->wallet_transaction_id)->type);
        $this->post('https://admin.localhost/settings/domains/orders/'.$order->uuid.'/irpower-confirm', ['expires_at' => '2027-10-26', 'provider_reference' => 'IRPOWER ticket confirmed 123456', 'verified' => 1])->assertStatus(409);
        $this->assertDatabaseCount('invoices', 1);
        Http::assertSent(fn ($r) => $r->url() === IrpowerProvider::URL && $r['command'] === 'RegisterDomain' && $r['contact']['first_name'] === 'Owner' && $r['contact']['phone'] === '+989121234567' && $r->hasHeader('X-Api-Key', 'irpower-test-key'));
    }

    public function test_uncertain_request_is_never_retried_or_refunded_automatically(): void
    {
        $order = $this->order(true);
        $this->fakeProvider('timeout');
        app(DomainFulfillmentService::class)->process($order);
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame('uncertain', $order->operations()->where('kind', 'register')->firstOrFail()->status);
        $this->assertSame('needs_review', $order->fresh()->status);
        $this->assertNull($order->fresh()->refund_transaction_id);
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($pair) => $pair[0]->url() === IrpowerProvider::URL && $pair[0]['command'] === 'RegisterDomain')->count());
    }

    public function test_explicit_rejection_releases_funds_once_and_hides_provider_details(): void
    {
        $order = $this->order(true);
        $this->fakeProvider('rejected');
        app(DomainFulfillmentService::class)->process($order);
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame(1, WalletTransaction::where('type', 'refund')->count());
        $this->assertSame(20000000, $this->customer->wallet->fresh()->balance);
        $this->assertStringNotContainsString('Private provider details', $order->fresh()->customer_message);
    }

    public function test_accepted_but_finally_rejected_registration_can_only_be_released_with_provider_evidence(): void
    {
        $order = $this->order(true);
        app(DomainFulfillmentService::class)->process($order);
        $this->actingAs(User::factory()->create(), 'admin')->post('https://admin.localhost/settings/domains/orders/'.$order->uuid.'/not-submitted', ['confirmed_not_executed' => 1])->assertSessionHasErrors('provider_reference');
        $this->assertNull($order->fresh()->refund_transaction_id);
        $this->post('https://admin.localhost/settings/domains/orders/'.$order->uuid.'/not-submitted', ['confirmed_not_executed' => 1, 'provider_reference' => 'Provider confirmed final rejection ticket 123456'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame(20000000, $this->customer->wallet->fresh()->balance);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_renewal_uses_original_provider_even_when_new_sales_are_disabled(): void
    {
        $domain = ManagedDomain::create(['uuid' => (string) Str::uuid(), 'customer_id' => $this->customer->id, 'name' => 'example.com', 'extension' => 'com', 'provider' => 'irpower', 'provider_id' => 'example.com', 'expires_at' => now()->addMonth(), 'nameservers' => ['ns1.example.net', 'ns2.example.net'], 'registrant' => ['name' => 'Owner Name']]);
        AppSetting::setValue('domains.routing_policy', 'reseller_world', 'string', 'domains');
        AppSetting::setValue('domains.irpower_enabled', false, 'boolean', 'domains');
        $order = app(DomainOrderService::class)->create($this->customer, $domain->name, 1, $domain);
        $this->assertSame('irpower', $order->provider);
        $this->assertSame(15000000, $order->total);
        $this->actingAs($this->customer, 'customer')->get('https://cp.localhost/domains/'.$domain->uuid)->assertOk();
    }

    public function test_irnic_returns_preliminary_result_and_fails_closed_for_errors(): void
    {
        $whois = new IrnicWhoisService;
        $this->assertTrue($whois->parse('example.ir', "% IRNIC Whois server\n%ERROR:101: no entries found\n")['available']);
        $this->assertFalse($whois->parse('example.ir', "% IRNIC Whois server\ndomain:\texample.ir\n")['available']);
        foreach (["% IRNIC Whois server\n%ERROR:201: query limit exceeded", '', "% IRNIC Whois server\ndomain: other.ir", "% IRNIC Whois server\ndomain: example.ir\n%ERROR:101: no entries found"] as $response) {
            try {
                $whois->parse('example.ir', $response);
                $this->fail('Ambiguous WHOIS response should not become available');
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_ir_search_uses_registry_and_single_handle_is_validated_before_payment(): void
    {
        $this->offer('irpower', 'register', 1350000, 'ir');
        $this->offer('irpower', 'renew', 1350000, 'ir');
        $this->mock(IrnicWhoisService::class)->shouldReceive('check')->with('example.ir')->andReturn(['domain' => 'example.ir', 'available' => true, 'premium' => false, 'preliminary' => true, 'checked_at' => now()->toIso8601String()]);
        $this->actingAs($this->customer, 'customer')->postJson('https://cp.localhost/domains/check', ['domain' => 'example.ir'])->assertOk()->assertJsonPath('data.preliminary', true)->assertJsonStructure(['data' => ['retail']]);
        $this->post('https://cp.localhost/domains/orders', ['domain' => 'example.ir', 'period' => 1])->assertRedirect();
        $order = DomainOrder::firstOrFail();
        $this->get('https://cp.localhost/domains/orders/'.$order->uuid)->assertOk()->assertSee('شناسه صاحب امتیاز ایرنیک');
        $this->patch('https://cp.localhost/domains/orders/'.$order->uuid, ['stage' => 'owner', 'holder' => 'ab123-irnic', 'use_holder_for_all' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('ab123-irnic', $order->fresh()->registrant['billing']);
        Http::assertSent(fn ($r) => $r['command'] === 'GetContactInfo' && $r['irnic_handle'] === 'ab123-irnic');
        $this->assertNull($order->fresh()->wallet_transaction_id);
    }

    public function test_ir_registration_sends_registry_roles_and_vat_without_using_world_lookup(): void
    {
        $this->offer('irpower', 'register', 1350000, 'ir');
        $this->offer('irpower', 'renew', 1350000, 'ir');
        AppSetting::setValue(AppSetting::TAX_ENABLED, true, 'boolean', 'billing');
        AppSetting::setValue(AppSetting::TAX_RATE_PERCENTAGE, 10, 'integer', 'billing');
        $this->mock(IrnicWhoisService::class)->shouldReceive('check')->with('example.ir')->andReturn(['domain' => 'example.ir', 'available' => true, 'premium' => false, 'preliminary' => true]);
        $order = app(DomainOrderService::class)->create($this->customer, 'example.ir', 1);
        $order->update(['registrant' => array_fill_keys(['holder', 'admin', 'tech', 'billing'], 'ab123-irnic'), 'nameservers' => ['ns1.example.net', 'ns2.example.net'], 'consented_at' => now()]);
        app(WalletService::class)->credit($this->customer, 2000000, 'Fixture');
        app(DomainOrderService::class)->pay($order->fresh(), null);
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame(1485000, $order->fresh()->total);
        $this->assertSame('needs_review', $order->fresh()->status);
        Http::assertSent(fn ($r) => $r->url() === IrpowerProvider::URL && $r['command'] === 'RegisterDomain' && $r['fields']['irnic_holder_handle'] === 'ab123-irnic' && $r['fields']['irnic_bill_handle'] === 'ab123-irnic' && $r['period'] === 1 && ! isset($r['contact']));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'reseller.world'));
    }

    public function test_irpower_renewal_is_confirmed_with_full_period_and_only_one_charge(): void
    {
        $owned = ManagedDomain::create(['uuid' => (string) Str::uuid(), 'customer_id' => $this->customer->id, 'name' => 'example.com', 'extension' => 'com', 'provider' => 'irpower', 'provider_id' => 'example.com', 'expires_at' => now()->addMonth(), 'nameservers' => ['ns1.example.net', 'ns2.example.net'], 'registrant' => ['name' => 'Owner Name']]);
        $order = app(DomainOrderService::class)->create($this->customer, 'example.com', 1, $owned);
        $order->update(['consented_at' => now()]);
        app(WalletService::class)->credit($this->customer, 20000000, 'Fixture');
        app(DomainOrderService::class)->pay($order->fresh(), null);
        app(DomainFulfillmentService::class)->process($order->fresh());
        $this->assertSame('needs_review', $order->fresh()->status);
        app(DomainFulfillmentService::class)->confirmIrpower($order->fresh(), '2027-11-26', 'Provider renewal confirmation 123456', User::factory()->create()->id);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('2027-11-26', $owned->fresh()->expires_at->toDateString());
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, WalletTransaction::where('type', 'charge')->count());
        Http::assertSent(fn ($r) => $r['command'] === 'RenewDomain');
        Http::assertNotSent(fn ($r) => $r['command'] === 'RegisterDomain');
    }

    public function test_premium_and_unavailable_names_do_not_reserve_funds_through_irpower(): void
    {
        $order = $this->order();
        app(WalletService::class)->credit($this->customer, 20000000, 'Fixture');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['domain' => 'example.com', 'available' => true, 'premium' => true])]);
        try {
            app(DomainOrderService::class)->pay($order, null);
            $this->fail('Premium domain must not charge from static IRPOWER price');
        } catch (ValidationException) {
            $this->assertNull($order->fresh()->wallet_transaction_id);
            $this->assertSame(20000000, $this->customer->wallet->fresh()->balance);
        }
    }

    public function test_missing_premium_flag_cannot_authorize_a_static_irpower_purchase(): void
    {
        $order = $this->order();
        app(WalletService::class)->credit($this->customer, 20000000, 'Fixture');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['domain' => 'example.com', 'available' => true])]);
        $this->actingAs($this->customer, 'customer')->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])->assertOk()->assertJsonPath('data.premium', null)->assertJsonMissingPath('data.retail');
        try {
            app(DomainOrderService::class)->pay($order, null);
            $this->fail('Missing premium classification must not become a standard-priced purchase');
        } catch (ValidationException) {
            $this->assertNull($order->fresh()->wallet_transaction_id);
        }
    }

    public function test_ir_handle_permission_failure_preserves_checkout_without_charge(): void
    {
        $this->offer('irpower', 'register', 1350000, 'ir');
        $this->offer('irpower', 'renew', 1350000, 'ir');
        $order = app(DomainOrderService::class)->create($this->customer, 'example.ir', 1);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([IrpowerProvider::URL => Http::response(['success' => true, 'result' => ['relations' => ['holder' => false]]])]);
        $this->actingAs($this->customer, 'customer')->patch('https://cp.localhost/domains/orders/'.$order->uuid, ['stage' => 'owner', 'holder' => 'ab123-irnic', 'use_holder_for_all' => 1])->assertSessionHasErrors('holder');
        $this->assertNull($order->fresh()->registrant);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_ir_handle_lookup_timeout_shows_recoverable_validation_and_preserves_input(): void
    {
        $this->offer('irpower', 'register', 1350000, 'ir');
        $this->offer('irpower', 'renew', 1350000, 'ir');
        $order = app(DomainOrderService::class)->create($this->customer, 'example.ir', 1);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([IrpowerProvider::URL => Http::failedConnection()]);
        $this->actingAs($this->customer, 'customer')->patch('https://cp.localhost/domains/orders/'.$order->uuid, ['stage' => 'owner', 'holder' => 'ab123-irnic', 'use_holder_for_all' => 1])->assertRedirect()->assertSessionHasErrors('holder')->assertSessionHas('_old_input.holder', 'ab123-irnic');
        $this->assertNull($order->fresh()->wallet_transaction_id);
    }

    public function test_nameserver_update_is_read_back_without_tracking_id_or_repeat_mutation(): void
    {
        $domain = ManagedDomain::create(['uuid' => (string) Str::uuid(), 'customer_id' => $this->customer->id, 'name' => 'example.com', 'extension' => 'com', 'provider' => 'irpower', 'provider_id' => 'example.com', 'expires_at' => now()->addYear(), 'nameservers' => ['ns1.old.net', 'ns2.old.net']]);
        app(DomainManagementService::class)->nameservers($domain, ['ns1.example.net', 'ns2.example.net']);
        $operation = DomainOperation::firstOrFail();
        $this->assertSame('done', $operation->status);
        $this->assertSame(['ns1.example.net', 'ns2.example.net'], $domain->fresh()->nameservers);
        Http::assertSentCount(2);
    }

    public function test_initial_price_import_is_idempotent_and_existing_prices_are_not_overwritten(): void
    {
        $catalog = app(IrpowerCatalogService::class);
        $catalog->import();
        $ir = DomainProduct::where('provider', 'irpower')->where('extension', 'ir')->where('operation', 'register')->firstOrFail();
        $this->assertSame(1080000, $ir->supplier_price);
        $this->assertFalse($ir->enabled);
        $ir->update(['supplier_price' => 1200000]);
        $this->assertSame(0, $catalog->import());
        $this->assertSame(1200000, $ir->fresh()->supplier_price);
    }

    public function test_key_is_encrypted_and_not_flashed_on_invalid_admin_settings(): void
    {
        $this->assertStringNotContainsString('irpower-test-key', DB::table('app_settings')->where('key', AppSetting::IRPOWER_API_KEY)->value('value'));
        $this->actingAs(User::factory()->create(), 'admin')->patch('https://admin.localhost/settings/domains', ['enabled' => 1, 'irpower_api_key' => 'new-secret-key', 'routing_policy' => 'invalid'])->assertSessionHasErrors('routing_policy')->assertSessionMissing('_old_input.irpower_api_key');
        $this->assertSame('irpower-test-key', AppSetting::irpowerKey());
        $this->patch('https://admin.localhost/settings/domains', ['enabled' => 1, 'irpower_api_key' => 'new-secret-key', 'irpower_enabled' => 1, 'routing_policy' => 'cheapest'])->assertRedirect()->assertSessionHasNoErrors();
        $audit = AdminAuditLog::where('route_name', 'admin.settings.domains.update')->latest('id')->firstOrFail();
        $this->assertSame('[REDACTED]', data_get($audit->metadata, 'input.irpower_api_key'));
        $this->assertStringNotContainsString('new-secret-key', json_encode($audit->metadata));
        $this->get('https://admin.localhost/settings/domains')->assertOk()->assertDontSee('new-secret-key');
    }

    public function test_admin_can_set_provider_specific_manual_cost_and_expiry(): void
    {
        $product = DomainProduct::where('provider', 'irpower')->where('operation', 'register')->firstOrFail();
        $this->actingAs(User::factory()->create(), 'admin')->patch('https://admin.localhost/settings/domains/products/'.$product->id, ['enabled' => 1, 'supplier_toman' => 700000, 'supplier_confirmed' => 1, 'price_valid_until' => now()->addDays(20)->toDateString(), 'retail_toman' => 900000, 'minimum_margin_toman' => 100000, 'markup_percent' => 25, 'automatic_pricing' => 0, 'contact_mode' => 'contact'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(7000000, $product->fresh()->supplier_price);
        $this->assertSame(9000000, $product->fresh()->retail_price);
        $this->get('https://admin.localhost/settings/domains/commerce?extension=com')->assertOk()->assertSee('IRPOWER')->assertSee('Reseller.world');
    }
}
