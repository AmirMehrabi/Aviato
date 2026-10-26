<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\User;
use App\Services\Domains\DomainAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        AppSetting::setValue(AppSetting::DOMAIN_RESELLER_TOKEN, 'test-provider-token', 'string', 'domains');
        AppSetting::setValue(AppSetting::DOMAIN_MANAGEMENT_ENABLED, true, 'boolean', 'domains');
    }

    private function selectedCustomer(): Customer
    {
        $customer = Customer::factory()->create();
        $customer->forceFill(['domain_management_enabled' => true])->save();

        return $customer;
    }

    public function test_feature_is_hidden_and_inaccessible_without_customer_selection(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')->get('https://cp.localhost/dashboard')
            ->assertOk()->assertDontSee(route('customer.domains.index', [], false), false);
        $this->get('https://cp.localhost/domains')->assertForbidden();
        $this->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_selected_customer_sees_search_in_navigation_and_can_open_page(): void
    {
        $this->actingAs($this->selectedCustomer(), 'customer')->get('https://cp.localhost/domains')
            ->assertOk()->assertSee('نام بعدی شما در اینترنت چیست؟')->assertSee('domain-name')
            ->assertSee(route('customer.domains.index', [], false), false)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        Http::assertNothingSent();
    }

    public function test_available_response_is_normalized_without_exposing_wholesale_prices(): void
    {
        Http::fake([DomainAvailabilityService::BASE_URL.'/*' => Http::response([
            'domain' => 'example.com', 'available' => true, 'premium' => true, 'price' => '33650000', 'base_price' => '33650000',
        ])]);
        $this->actingAs($this->selectedCustomer(), 'customer')
            ->postJson('https://cp.localhost/domains/check', ['domain' => ' HTTPS://WWW.Example.COM/some-page?q=1 '])
            ->assertOk()->assertJsonPath('data.domain', 'example.com')->assertJsonPath('data.available', true)
            ->assertJsonPath('data.premium', true)->assertJsonMissingPath('data.price')->assertDontSee('test-provider-token');
        Http::assertSent(fn ($request) => $request->url() === DomainAvailabilityService::BASE_URL.'/domain/example.com/check'
            && $request->hasHeader('Authorization', 'Bearer test-provider-token')
            && $request['period'] === 1 && $request['type'] === 'register');
    }

    public function test_unavailable_domain_and_no_javascript_submission_are_supported(): void
    {
        Http::fake(['*' => Http::response(['domain' => 'example.com', 'available' => false, 'reason' => 'Domain exists'])]);
        $this->actingAs($this->selectedCustomer(), 'customer')
            ->post('https://cp.localhost/domains/check', ['domain' => 'example'])
            ->assertOk()->assertViewHas('result', fn ($result) => $result['available'] === false && $result['domain'] === 'example.com');
    }

    public function test_valid_results_are_cached_but_revocation_blocks_even_cached_lookups(): void
    {
        Http::fake(['*' => Http::response(['domain' => 'example.com', 'available' => true])]);
        $customer = $this->selectedCustomer();
        $this->actingAs($customer, 'customer');
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])->assertOk();
        }
        Http::assertSentCount(1);
        $customer->forceFill(['domain_management_enabled' => false])->save();
        $this->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])->assertForbidden();
        Http::assertSentCount(1);
    }

    public function test_invalid_domains_do_not_reach_provider(): void
    {
        $this->actingAs($this->selectedCustomer(), 'customer');
        foreach (['', 'my domain.com', '-invalid.com', '127.0.0.1', 'example.com/path', 'example.com@evil.com', str_repeat('a', 64).'.com', ['example.com']] as $domain) {
            $this->postJson('https://cp.localhost/domains/check', ['domain' => $domain])->assertUnprocessable()->assertJsonValidationErrors('domain');
        }
        Http::assertNothingSent();
    }

    public function test_normalization_preserves_www_as_a_registered_label_and_supports_idn(): void
    {
        Http::fake(['*' => fn ($request) => Http::response([
            'domain' => basename(dirname($request->url())), 'available' => false,
        ])]);
        $this->actingAs($this->selectedCustomer(), 'customer')
            ->postJson('https://cp.localhost/domains/check', ['domain' => 'www.com'])
            ->assertOk()->assertJsonPath('data.domain', 'www.com');
        if (function_exists('idn_to_ascii')) {
            $this->postJson('https://cp.localhost/domains/check', ['domain' => 'bücher.com'])
                ->assertOk()->assertJsonPath('data.domain', 'xn--bcher-kva.com');
        }
    }

    public function test_provider_failures_and_malformed_results_never_claim_availability_or_leak_details(): void
    {
        $this->actingAs($this->selectedCustomer(), 'customer');
        foreach ([Http::response(['message' => 'secret upstream error'], 401), Http::response(['available' => 'false']), Http::response(['domain' => 'other.com', 'available' => true]), Http::response('invalid json'), Http::failedConnection()] as $response) {
            Http::fake(['*' => $response]);
            $this->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])
                ->assertStatus(503)->assertJsonMissingPath('data.available')->assertDontSee('secret upstream error')->assertDontSee('test-provider-token');
        }
    }

    public function test_global_disable_and_suspended_customer_block_access(): void
    {
        $customer = $this->selectedCustomer();
        $customer->suspend();
        $this->actingAs($customer, 'customer')->get('https://cp.localhost/domains')->assertForbidden();
        $customer->activate();
        AppSetting::setValue(AppSetting::DOMAIN_MANAGEMENT_ENABLED, false, 'boolean', 'domains');
        $this->get('https://cp.localhost/domains')->assertForbidden();
        $this->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_unsupported_extensions_have_actionable_validation_instead_of_unavailable_result(): void
    {
        $this->actingAs($this->selectedCustomer(), 'customer');
        foreach ([
            Http::response(['error' => ['msgType' => 'msg.notSupportedTld']], 406),
            Http::response(['error' => ['details' => ['Tld is not supported for this reseller.']]], 422),
        ] as $response) {
            Http::fake(['*' => $response]);
            $this->postJson('https://cp.localhost/domains/check', ['domain' => 'example.unsupported'])
                ->assertUnprocessable()->assertJsonValidationErrors('domain')->assertJsonMissingPath('data.available');
        }
    }

    public function test_cached_availability_expires_and_does_not_survive_token_rotation(): void
    {
        Http::fake(['*' => Http::response(['domain' => 'example.com', 'available' => true])]);
        $this->actingAs($this->selectedCustomer(), 'customer');
        $this->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])->assertOk();
        $this->travel(31)->seconds();
        $this->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])->assertOk();
        AppSetting::setValue(AppSetting::DOMAIN_RESELLER_TOKEN, 'rotated-token', 'string', 'domains');
        $this->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])->assertOk();
        Http::assertSentCount(3);
    }

    public function test_domain_access_is_not_customer_mass_assignable(): void
    {
        $customer = Customer::factory()->create();
        $this->assertFalse($customer->isFillable('domain_management_enabled'));
        $this->assertFalse($customer->fresh()->domain_management_enabled);
    }

    public function test_guest_and_admin_guard_cannot_use_customer_lookup(): void
    {
        $this->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'admin')
            ->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_production_customer_alias_returns_json_for_search_and_errors(): void
    {
        $this->postJson('https://my.aviato.ir/domains/check', ['domain' => 'example.com'])->assertUnauthorized();
        $this->actingAs($this->selectedCustomer(), 'customer')
            ->postJson('https://my.aviato.ir/domains/check', ['domain' => 'bad domain'])->assertUnprocessable()->assertJsonValidationErrors('domain');
        Http::fake(['*' => Http::response(['domain' => 'example.com', 'available' => true])]);
        $this->postJson('https://my.aviato.ir/domains/check', ['domain' => 'example.com'])
            ->assertOk()->assertJsonPath('data.available', true);
    }

    public function test_admin_can_select_search_and_revoke_customers(): void
    {
        $customer = Customer::factory()->create(['name' => 'Selected customer']);
        Customer::factory()->create(['name' => 'Other customer']);
        $this->actingAs(User::factory()->create(), 'admin')
            ->get('https://admin.localhost/settings')->assertOk()->assertSee('دامنه‌ها و دسترسی مشتریان');
        $this->patch('https://admin.localhost/settings/domains/customers/'.$customer->id, ['enabled' => 1])->assertRedirect();
        $this->assertTrue($customer->fresh()->domain_management_enabled);
        $this->get('https://admin.localhost/settings/domains?q=Selected')->assertOk()->assertSee('Selected customer')->assertDontSee('Other customer');
        $this->get('https://admin.localhost/settings/domains?access=selected')->assertOk()->assertSee('Selected customer')->assertDontSee('Other customer');
        $this->patch('https://admin.localhost/settings/domains/customers/'.$customer->id, ['enabled' => 0])->assertRedirect();
        $this->assertFalse($customer->fresh()->domain_management_enabled);
    }

    public function test_token_is_encrypted_masked_preserved_and_redacted_in_admin_audit(): void
    {
        $this->actingAs(User::factory()->create(), 'admin')
            ->patch('https://admin.localhost/settings/domains', ['enabled' => 1, 'domain_reseller_token' => 'Bearer replacement-secret'])
            ->assertRedirect('https://admin.localhost/settings/domains')->assertSessionHasNoErrors();
        $this->assertSame('replacement-secret', AppSetting::domainResellerToken());
        $stored = AppSetting::where('key', AppSetting::DOMAIN_RESELLER_TOKEN)->firstOrFail()->value;
        $this->assertStringStartsWith('encrypted:', $stored);
        $this->assertStringNotContainsString('replacement-secret', $stored);
        $this->get('https://admin.localhost/settings/domains')->assertOk()->assertDontSee('replacement-secret');
        $this->assertStringNotContainsString('replacement-secret', AdminAuditLog::latest('id')->firstOrFail()->metadata ? json_encode(AdminAuditLog::latest('id')->first()->metadata) : '');
        $this->patch('https://admin.localhost/settings/domains', ['enabled' => 0, 'domain_reseller_token' => ''])->assertSessionHasNoErrors();
        $this->assertSame('replacement-secret', AppSetting::domainResellerToken());
        $this->assertFalse(AppSetting::domainManagementEnabled());
    }

    public function test_token_never_flashes_into_session_after_validation_error(): void
    {
        $this->actingAs(User::factory()->create(), 'admin')
            ->from('https://admin.localhost/settings/domains')
            ->patch('https://admin.localhost/settings/domains', ['enabled' => 'invalid', 'domain_reseller_token' => 'submitted-secret'])
            ->assertSessionHasErrors('enabled')->assertSessionMissing('_old_input.domain_reseller_token');
    }

    public function test_support_admin_cannot_read_secrets_or_change_domain_access(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'support']), 'admin')
            ->get('https://admin.localhost/settings/domains')->assertForbidden();
        $this->patch('https://admin.localhost/settings/domains', ['enabled' => 1])->assertForbidden();
        $this->patch('https://admin.localhost/settings/domains/customers/'.$customer->id, ['enabled' => 1])->assertForbidden();
        $this->assertFalse($customer->fresh()->domain_management_enabled);
    }

    public function test_lookup_is_rate_limited_per_customer(): void
    {
        Http::fake(['*' => Http::response(['domain' => 'example.com', 'available' => true])]);
        $this->actingAs($this->selectedCustomer(), 'customer');
        for ($i = 0; $i < 15; $i++) {
            $this->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])->assertOk();
        }
        $this->postJson('https://cp.localhost/domains/check', ['domain' => 'example.com'])->assertTooManyRequests();
    }
}
