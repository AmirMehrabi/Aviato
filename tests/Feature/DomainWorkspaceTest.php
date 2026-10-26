<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\DomainOrder;
use App\Models\DomainProduct;
use App\Models\Invoice;
use App\Models\ManagedDomain;
use App\Models\Project;
use App\Services\Domains\DomainOrderService;
use App\Services\ProjectAccessService;
use App\Services\ProjectLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Customer $owner;

    private Project $first;

    private Project $second;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        Http::preventStrayRequests();
        AppSetting::setValue(AppSetting::DOMAIN_RESELLER_TOKEN, 'test-token', 'string', 'domains');
        AppSetting::setValue(AppSetting::DOMAIN_MANAGEMENT_ENABLED, true, 'boolean', 'domains');
        AppSetting::setValue('domains.sales_enabled', true, 'boolean', 'domains');
        AppSetting::setValue(AppSetting::BILLING_CURRENCY, 'IRR', 'string', 'billing');
        $this->owner = Customer::factory()->create(['name' => 'آرمان']);
        $this->owner->forceFill(['domain_management_enabled' => true])->save();
        $this->first = $this->owner->ownedProjects()->firstOrFail();
        $this->first->update(['name' => 'فروشگاه']);
        $this->second = $this->owner->ownedProjects()->create(['name' => 'وبلاگ']);
        $this->second->members()->create(['customer_id' => $this->owner->id, 'role' => 'owner']);
        foreach (['register', 'renew'] as $operation) {
            DomainProduct::create(['extension' => 'com', 'operation' => $operation, 'period' => 1, 'enabled' => true, 'supplier_price' => 8000000, 'retail_price' => 10000000, 'synced_at' => now()]);
        }
    }

    private function domain(?Project $project = null, string $name = 'shop.com', array $attributes = []): ManagedDomain
    {
        return ManagedDomain::create($attributes + [
            'uuid' => (string) Str::uuid(), 'customer_id' => $this->owner->id, 'project_id' => ($project ?? $this->first)->id,
            'name' => $name, 'extension' => 'com', 'status' => 'active', 'expires_at' => now()->addYear(),
            'nameservers' => ['ns1.example.com', 'ns2.example.com'], 'registrant' => ['name' => 'مالک دامنه', 'email' => 'private@example.com'],
        ]);
    }

    private function signIn(?Customer $customer = null, ?Project $project = null): void
    {
        $this->actingAs($customer ?? $this->owner, 'customer')->withSession([ProjectAccessService::SESSION_KEY => ($project ?? $this->first)->id]);
    }

    public function test_empty_workspace_leads_with_search_and_populated_workspace_leads_with_management(): void
    {
        $this->signIn();
        $this->get('https://cp.localhost/domains')->assertOk()->assertViewHas('showSearch', true)->assertSee('نام بعدی شما در اینترنت چیست؟');
        $this->domain();
        $this->get('https://cp.localhost/domains')->assertOk()->assertViewHas('showSearch', false)
            ->assertSee('shop.com')->assertSee('دامنه‌های فضای کاری')->assertDontSee('نام بعدی شما در اینترنت چیست؟');
        $this->get('https://cp.localhost/domains?register=1')->assertOk()->assertViewHas('showSearch', true)->assertSee('name="workspace"', false);
        $this->get('https://cp.localhost/domains?view=orders')->assertOk()->assertSee('سفارش‌های دامنه');
    }

    public function test_workspace_switch_limits_domains_and_orders(): void
    {
        $this->domain();
        $this->domain($this->second, 'blog.com');
        $order = app(DomainOrderService::class)->create($this->owner, 'new.com', 1, project: $this->first);
        $this->signIn(project: $this->second);
        $this->get('https://cp.localhost/domains')->assertOk()->assertSee('blog.com')->assertDontSee('shop.com')->assertDontSee('new.com');
        $this->get('https://cp.localhost/domains?view=orders')->assertOk()->assertDontSee($order->domain);
        $this->signIn();
        $this->get('https://cp.localhost/domains')->assertOk()->assertSee('shop.com')->assertDontSee('blog.com')->assertSee('سفارش‌های در جریان');
        $this->get('https://cp.localhost/domains?view=orders')->assertOk()->assertSee('new.com');
    }

    public function test_pending_registration_is_the_next_action_instead_of_another_search(): void
    {
        app(DomainOrderService::class)->create($this->owner, 'pending.com', 1, project: $this->first);
        $this->signIn();
        $this->get('https://cp.localhost/domains')->assertOk()->assertViewHas('showSearch', false)
            ->assertSee('سفارش‌های در جریان')->assertSee('ادامه سفارش')->assertDontSee('نام بعدی شما در اینترنت چیست؟');
    }

    public function test_new_orders_keep_the_selected_workspace_and_stale_forms_do_not_purchase_elsewhere(): void
    {
        $this->signIn(project: $this->second);
        $this->post('https://cp.localhost/domains/orders', ['domain' => 'new.com', 'period' => 1, 'workspace' => $this->first->uuid])->assertStatus(409);
        $this->assertDatabaseCount('domain_orders', 0);
        $this->post('https://cp.localhost/domains/orders', ['domain' => 'new.com', 'period' => 1, 'workspace' => $this->second->uuid])->assertRedirect();
        $order = DomainOrder::firstOrFail();
        $this->assertSame($this->second->id, $order->project_id);
        $this->assertSame($this->owner->id, $order->customer_id);
        $this->get('https://cp.localhost/domains/orders/'.$order->uuid)->assertOk()->assertSee('وبلاگ')->assertSee('کیف پول شخصی');
        $other = app(DomainOrderService::class)->create($this->owner, 'new.com', 1, project: $this->first);
        $this->assertNotSame($other->id, $order->id);
    }

    public function test_bookmarks_open_the_right_workspace_but_old_forms_cannot_change_another_workspace(): void
    {
        $domain = $this->domain();
        $this->signIn(project: $this->second);
        $this->patch('https://cp.localhost/domains/'.$domain->uuid.'/auto-renew', ['enabled' => 0])->assertStatus(409);
        $this->get('https://cp.localhost/domains/'.$domain->uuid)->assertOk()->assertSessionHas(ProjectAccessService::SESSION_KEY, $this->first->id);
        $other = Customer::factory()->create();
        $other->forceFill(['domain_management_enabled' => true])->save();
        $this->signIn($other, $other->ensureDefaultProject());
        $this->get('https://cp.localhost/domains/'.$domain->uuid)->assertNotFound();
    }

    public function test_order_status_polling_does_not_change_the_selected_workspace(): void
    {
        $order = app(DomainOrderService::class)->create($this->owner, 'pending.com', 1, project: $this->first);
        $this->signIn(project: $this->second);
        $this->getJson('https://cp.localhost/domains/orders/'.$order->uuid)->assertOk()
            ->assertSessionHas(ProjectAccessService::SESSION_KEY, $this->second->id);
    }

    public function test_members_get_read_only_management_without_payment_or_saved_contacts(): void
    {
        $domain = $this->domain();
        $order = app(DomainOrderService::class)->create($this->owner, 'pending.com', 1, project: $this->first);
        $member = Customer::factory()->create();
        $this->first->members()->create(['customer_id' => $member->id, 'role' => 'viewer']);
        $this->signIn($member);
        $this->get('https://cp.localhost/domains')->assertOk()->assertSee('shop.com')->assertViewHas('canSearch', false);
        $this->get('https://cp.localhost/domains/'.$domain->uuid)->assertOk()->assertDontSee('private@example.com')
            ->assertDontSee('name="nameservers[]"', false)->assertDontSee('name="limit_toman"', false);
        $this->patch('https://cp.localhost/domains/'.$domain->uuid.'/nameservers', ['nameservers' => ['ns1.other.com', 'ns2.other.com']])->assertForbidden();
        $this->patch('https://cp.localhost/domains/'.$domain->uuid.'/auto-renew', ['enabled' => 0])->assertForbidden();
        $this->get('https://cp.localhost/domains/orders/'.$order->uuid)->assertOk()->assertViewIs('customer.domains.order-status')->assertDontSee('contact_id');
        $this->post('https://cp.localhost/domains/orders/'.$order->uuid.'/pay', ['terms' => 1])->assertForbidden();
        $this->postJson('https://cp.localhost/domains/check', ['domain' => 'new.com'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_admin_can_manage_nameservers_and_billing_members_can_only_view_financial_documents(): void
    {
        $domain = $this->domain();
        $member = Customer::factory()->create();
        $membership = $this->first->members()->create(['customer_id' => $member->id, 'role' => 'admin']);
        $this->signIn($member);
        $this->get('https://cp.localhost/domains/'.$domain->uuid)->assertOk()->assertViewHas('canManageDomain', true)->assertViewHas('canPayDomain', false);
        $order = app(DomainOrderService::class)->create($this->owner, 'billed.com', 1, project: $this->first);
        $invoice = Invoice::create(['customer_id' => $this->owner->id, 'kind' => 'domain', 'domain_order_id' => $order->id, 'number' => 'DOM-TEST', 'period_start' => now(), 'period_end' => now()->addYear(), 'issued_at' => now(), 'status' => 'issued', 'currency' => 'IRR', 'subtotal_amount' => 10000000, 'total_amount' => 10000000]);
        $order->update(['invoice_id' => $invoice->id]);
        $membership->update(['role' => 'billing']);
        $this->get('https://cp.localhost/invoices?tab=domains')->assertOk()->assertSee('DOM-TEST');
        $this->get('https://cp.localhost/domains/orders/'.$order->uuid.'/invoice')->assertOk()->assertSee('آرمان');
        $this->get('https://cp.localhost/invoices/'.$invoice->id)->assertOk();
        $membership->update(['role' => 'viewer']);
        $this->get('https://cp.localhost/domains/orders/'.$order->uuid.'/invoice')->assertForbidden();
        $this->get('https://cp.localhost/invoices?tab=domains')->assertNotFound();
    }

    public function test_expiry_filters_and_search_only_use_domains_in_this_workspace(): void
    {
        $this->domain(name: 'healthy.com');
        $this->domain(name: 'soon.com', attributes: ['expires_at' => now()->addDays(5)]);
        $this->domain(name: 'expired.com', attributes: ['expires_at' => now()->subDay()]);
        $this->domain($this->second, 'elsewhere.com', ['expires_at' => now()->addDays(2)]);
        $this->signIn();
        $this->get('https://cp.localhost/domains?filter=attention')->assertOk()->assertViewHas('ownedDomains', fn ($domains) => $domains->pluck('name')->all() === ['expired.com', 'soon.com']);
        $this->get('https://cp.localhost/domains?filter=expiring')->assertOk()->assertViewHas('ownedDomains', fn ($domains) => $domains->pluck('name')->all() === ['soon.com']);
        $this->get('https://cp.localhost/domains?q=HEALTHY')->assertOk()->assertViewHas('ownedDomains', fn ($domains) => $domains->pluck('name')->all() === ['healthy.com']);
    }

    public function test_backfill_creates_an_owned_workspace_when_none_exists(): void
    {
        $domain = $this->domain();
        DB::table('managed_domains')->where('id', $domain->id)->update(['project_id' => null]);
        DB::table('projects')->where('owner_customer_id', $this->owner->id)->delete();
        $migration = require database_path('migrations/2026_10_26_140000_scope_domains_to_projects.php');
        $migration->backfill();
        $project = $domain->fresh()->project;
        $this->assertSame($this->owner->id, $project->owner_customer_id);
        $this->assertSame('owner', $project->members()->where('customer_id', $this->owner->id)->value('role'));
        $this->assertSame('فضای کاری من', $project->name);
    }

    public function test_backfill_uses_first_owned_workspace_instead_of_default_active_or_joined_workspace(): void
    {
        $domain = $this->domain(attributes: ['auto_renew' => true, 'auto_renew_limit' => 5000000, 'auto_renew_consented_at' => now()]);
        $order = app(DomainOrderService::class)->create($this->owner, $domain->name, 1, $domain);
        $this->first->update(['created_at' => now()->subYear(), 'is_default' => false]);
        $this->second->update(['is_default' => true]);
        $joined = Customer::factory()->create()->ensureDefaultProject();
        $joined->update(['created_at' => now()->subYears(2)]);
        $joined->members()->create(['customer_id' => $this->owner->id, 'role' => 'admin']);
        $migration = require database_path('migrations/2026_10_26_140000_scope_domains_to_projects.php');
        DB::table('managed_domains')->where('id', $domain->id)->update(['project_id' => null]);
        DB::table('domain_orders')->where('id', $order->id)->update(['project_id' => null]);
        $migration->backfill();
        $migration->backfill();
        $this->assertSame($this->first->id, $domain->fresh()->project_id);
        $this->assertSame($this->first->id, $order->fresh()->project_id);
        $this->assertSame($this->owner->id, $domain->fresh()->customer_id);
        $this->assertSame(5000000, $domain->fresh()->auto_renew_limit);
        $this->assertTrue($domain->fresh()->auto_renew);
        $this->assertSame($order->total, $order->fresh()->total);
        $this->assertArrayHasKey('domains', app(ProjectLifecycleService::class)->deletionBlockers($this->first, $this->owner));
    }
}
