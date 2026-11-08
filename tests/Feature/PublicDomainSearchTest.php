<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\DomainProduct;
use App\Services\Domains\DomainAvailabilityService;
use App\Services\Domains\DomainProvider;
use App\Services\Domains\DomainProviderBudget;
use App\Services\Domains\DomainProviderBusy;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicDomainSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['domain-search.store' => 'array']);
        Http::preventStrayRequests();
        AppSetting::setValue(AppSetting::DOMAIN_RESELLER_TOKEN, 'private-test-token', 'string', 'domains');
        AppSetting::setValue(AppSetting::DOMAIN_MANAGEMENT_ENABLED, true, 'boolean', 'domains');
        AppSetting::setValue('domains.sales_enabled', true, 'boolean', 'domains');
        AppSetting::setValue(AppSetting::BILLING_CURRENCY, 'IRR', 'string', 'billing');
        foreach (['register', 'renew'] as $operation) {
            DomainProduct::create(['extension' => 'com', 'operation' => $operation, 'period' => 1, 'enabled' => true,
                'supplier_price' => 8000000, 'retail_price' => 10000000, 'synced_at' => now()]);
        }
    }

    private function fakeDomain(bool $available = true, ?bool $premium = false): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['domain' => 'example.com', 'available' => $available, 'premium' => $premium, 'price' => 8000000])]);
    }

    public function test_search_normalizes_and_hands_off_only_purchasable_domains_without_disclosing_costs(): void
    {
        $this->fakeDomain();
        $next = route('customer.domains.index', ['register' => 1, 'domain' => 'example.com', 'from' => 'homepage']);
        $this->postJson('/domains/check', ['domain' => ' HTTPS://WWW.Example.COM/page '])
            ->assertOk()->assertJsonPath('data.domain', 'example.com')->assertJsonPath('continue_url', $next)
            ->assertJsonMissingPath('data.price')->assertJsonMissingPath('data.retail.supplier_price')
            ->assertJsonStructure(['data' => ['retail' => ['total', 'renewal']]])->assertDontSee('private-test-token');
        $this->postJson('/domains/check', ['domain' => 'example.com'])->assertOk();
        Http::assertSentCount(1);
        // The normal auth flow stores the domain-bearing destination, not a POST.
        $this->get($next)->assertRedirect();
        parse_str(parse_url(session('url.intended'), PHP_URL_QUERY), $query);
        $this->assertSame(['domain' => 'example.com', 'from' => 'homepage', 'register' => '1'], $query);
    }

    public function test_invalid_or_unsupported_input_never_becomes_an_available_result(): void
    {
        $this->postJson('/domains/check', ['domain' => ['example.com']])->assertUnprocessable();
        $this->postJson('/domains/check', ['domain' => '127.0.0.1'])->assertUnprocessable();
        Http::assertNothingSent();
        Http::fake(['*' => Http::response(['error' => ['msgType' => 'msg.notSupportedTld']], 422)]);
        $this->postJson('/domains/check', ['domain' => 'example.unknown'])->assertUnprocessable();
    }

    public function test_unavailable_and_premium_names_are_not_redirected(): void
    {
        $this->fakeDomain(false);
        $this->postJson('/domains/check', ['domain' => 'example.com'])
            ->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('continue_url', null);
        Cache::flush();
        $this->fakeDomain(true, true);
        $this->postJson('/domains/check', ['domain' => 'example.com'])
            ->assertOk()->assertJsonPath('data.premium', true)->assertJsonPath('continue_url', null);
    }

    public function test_unknown_premium_status_and_disabled_sales_do_not_offer_checkout(): void
    {
        $this->fakeDomain(true, null);
        $this->postJson('/domains/check', ['domain' => 'example.com'])->assertOk()->assertJsonPath('continue_url', null);
        Cache::flush();
        AppSetting::setValue('domains.sales_enabled', false, 'boolean', 'domains');
        $this->fakeDomain();
        $this->postJson('/domains/check', ['domain' => 'example.com'])->assertOk()->assertJsonPath('continue_url', null);
    }

    public function test_provider_failure_is_a_retryable_error_without_raw_provider_details(): void
    {
        Http::fake(['*' => Http::response(['message' => 'secret provider failure'], 500)]);
        $this->postJson('/domains/check', ['domain' => 'example.com'])
            ->assertStatus(503)->assertJsonMissingPath('data')->assertDontSee('secret provider failure');
    }

    public function test_provider_429_stops_other_lookups_and_checkout_but_allows_cached_answers(): void
    {
        $rateLimited = false;
        Http::fake(function () use (&$rateLimited) {
            return $rateLimited
                ? Http::response([], 429, ['Retry-After' => '120'])
                : Http::response(['domain' => 'example.com', 'available' => true, 'premium' => false]);
        });
        app(DomainAvailabilityService::class)->check('example.com');
        $rateLimited = true;
        $this->postJson('/domains/check', ['domain' => 'other.com'])->assertStatus(429)->assertHeader('Retry-After', '120');
        $this->postJson('/domains/check', ['domain' => 'third.com'])->assertStatus(429);
        $this->postJson('/domains/check', ['domain' => 'example.com'])->assertOk();
        Http::assertSentCount(2);
        try {
            app(DomainProvider::class)->check('fourth.com', 1, 'register');
            $this->fail('Checkout must respect the provider cooldown.');
        } catch (DomainProviderBusy $exception) {
            $this->assertFalse($exception->requestSent);
        }
        Http::assertSentCount(2);
    }

    public function test_shared_search_budget_preserves_checkout_capacity_and_resets_after_window(): void
    {
        config(['domain-search.reseller_world.per_minute' => 3, 'domain-search.reseller_world.search_per_minute' => 2]);
        $budget = app(DomainProviderBudget::class);
        $budget->reserve('reseller_world', true);
        $budget->reserve('reseller_world', true);
        try {
            $budget->reserve('reseller_world', true);
            $this->fail('Search must leave capacity for checkout.');
        } catch (DomainProviderBusy $exception) {
            $this->assertGreaterThan(0, $exception->retryAfter);
        }
        $budget->reserve('reseller_world');
        try {
            $budget->reserve('reseller_world');
            $this->fail('All calls must respect the shared ceiling.');
        } catch (DomainProviderBusy) {
            $this->travel(61)->seconds();
            $budget->reserve('reseller_world', true);
            $this->travelBack();
        }
    }

    public function test_public_search_has_a_separate_visitor_rate_limit_even_for_cached_answers(): void
    {
        $this->fakeDomain();
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/domains/check', ['domain' => 'example.com'])->assertOk();
        }
        $this->postJson('/domains/check', ['domain' => 'example.com'])->assertStatus(429)->assertHeader('Retry-After');
        Http::assertSentCount(1);
    }

    public function test_public_and_portal_searches_share_the_validated_availability_cache(): void
    {
        $this->fakeDomain();
        $this->postJson('/domains/check', ['domain' => 'example.com'])->assertOk();
        $customer = Customer::factory()->create(['domain_management_enabled' => true]);
        $this->actingAs($customer, 'customer')->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])
            ->assertOk()->assertJsonPath('data.available', true);
        Http::assertSentCount(1);
    }

    public function test_provider_http_date_cooldown_and_shorter_followup_never_shorten_the_pause(): void
    {
        $budget = app(DomainProviderBudget::class);
        foreach ([now()->addSeconds(120)->toRfc7231String(), '5'] as $retry) {
            try {
                $response = new \Illuminate\Http\Client\Response(new Response(429, ['Retry-After' => $retry]));
                $budget->observe('reseller_world', $response);
            } catch (DomainProviderBusy) {
                // Both responses were received; the longer cooldown wins.
            }
        }
        try {
            $budget->reserve('reseller_world');
            $this->fail('The shared provider pause must still apply.');
        } catch (DomainProviderBusy $exception) {
            $this->assertGreaterThanOrEqual(119, $exception->retryAfter);
        }
    }

    public function test_unselected_customer_gets_a_useful_access_message_without_enabling_purchase(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')->get(route('customer.domains.index', ['from' => 'homepage', 'domain' => 'example.com']))
            ->assertForbidden()->assertSee('خرید دامنه برای حساب شما هنوز فعال نیست.')->assertSee('example.com');
        $this->assertFalse($customer->fresh()->canPurchaseDomains());
    }
}
