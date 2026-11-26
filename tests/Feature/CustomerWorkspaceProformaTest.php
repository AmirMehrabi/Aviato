<?php

namespace Tests\Feature;

use App\Exceptions\VmPriceUnavailableException;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ResourceRate;
use App\Models\UsageAccrual;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\ProjectAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerWorkspaceProformaTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER_URL = 'https://cp.localhost';

    private const TRAFFIC_NOTICE = 'هزینه مصرف ترافیک شبکه در این پیش‌فاکتور لحاظ نشده است.';

    public function test_billing_members_can_view_proformas_at_any_vm_scope_while_other_roles_keep_existing_access(): void
    {
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $vm = $this->vm($project, 'workspace-vm');
        $path = '/projects/'.$project->uuid;
        $link = 'href="'.$path.'/proforma"';

        foreach ([
            [ProjectMember::ROLE_OWNER, ProjectMember::VM_ACCESS_ALL, true],
            [ProjectMember::ROLE_ADMIN, ProjectMember::VM_ACCESS_ALL, true],
            [ProjectMember::ROLE_ADMIN, ProjectMember::VM_ACCESS_OWN, false],
            [ProjectMember::ROLE_ADMIN, ProjectMember::VM_ACCESS_SPECIFIC, false],
            [ProjectMember::ROLE_BILLING, ProjectMember::VM_ACCESS_ALL, true],
            [ProjectMember::ROLE_BILLING, ProjectMember::VM_ACCESS_OWN, true],
            [ProjectMember::ROLE_BILLING, ProjectMember::VM_ACCESS_SPECIFIC, true],
            [ProjectMember::ROLE_MEMBER, ProjectMember::VM_ACCESS_ALL, false],
            [ProjectMember::ROLE_VIEWER, ProjectMember::VM_ACCESS_ALL, false],
        ] as [$role, $scope, $allowed]) {
            $customer = $owner;
            if ($role !== ProjectMember::ROLE_OWNER) {
                $customer = Customer::factory()->create();
                $membership = $project->members()->create([
                    'customer_id' => $customer->id, 'role' => $role, 'vm_access_scope' => $scope,
                ]);
                if ($scope === ProjectMember::VM_ACCESS_SPECIFIC) {
                    $membership->specificVirtualMachines()->attach($vm);
                }
            }

            $this->actingAs($customer, 'customer');
            $index = $this->get(self::CUSTOMER_URL.'/projects')->assertOk();
            $billing = $this->get(self::CUSTOMER_URL.$path.'?section=billing')->assertOk();
            $document = $this->get(self::CUSTOMER_URL.$path.'/proforma');

            if ($allowed) {
                $index->assertSee($link, false);
                $billing->assertSee($link, false);
                $document->assertOk()->assertSee('workspace-vm');
            } else {
                $index->assertDontSee($link, false);
                $billing->assertDontSee($link, false);
                $document->assertNotFound();
            }
        }

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(self::CUSTOMER_URL.$path.'/proforma')->assertNotFound();
    }

    public function test_revocation_and_unknown_or_deleted_workspaces_return_not_found(): void
    {
        $owner = Customer::factory()->create();
        $project = $owner->ownedProjects()->create(['name' => 'Shared workspace']);
        $admin = Customer::factory()->create();
        $membership = $project->members()->create(['customer_id' => $admin->id, 'role' => ProjectMember::ROLE_ADMIN]);
        $url = self::CUSTOMER_URL.'/projects/'.$project->uuid.'/proforma';

        $this->actingAs($admin, 'customer')->get($url)->assertOk();
        $membership->delete();
        $this->get($url)->assertNotFound();
        $this->get(self::CUSTOMER_URL.'/projects/'.Str::uuid().'/proforma')->assertNotFound();

        $project->delete();
        $this->actingAs($owner, 'customer')->get($url)->assertNotFound();
    }

    public function test_customer_authentication_and_current_portal_host_are_required(): void
    {
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $path = '/projects/'.$project->uuid;

        $this->get(self::CUSTOMER_URL.$path.'/proforma')->assertRedirect(self::CUSTOMER_URL.'/login');
        $this->actingAs(User::factory()->create(), 'admin')
            ->get(self::CUSTOMER_URL.$path.'/proforma')->assertRedirect(self::CUSTOMER_URL.'/login');
        $this->get('https://admin.localhost'.$path.'/proforma')->assertNotFound();

        $this->actingAs($owner, 'customer')->get('https://my.aviato.ir/projects')
            ->assertOk()->assertSee('href="'.$path.'/proforma"', false);
        $response = $this->get('https://my.aviato.ir'.$path.'/proforma')
            ->assertOk()->assertSee('href="'.$path.'?section=billing"', false);
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function test_document_uses_the_url_workspace_and_existing_running_rates_without_mutating_billing(): void
    {
        $owner = Customer::factory()->create();
        $active = $owner->ensureDefaultProject();
        $project = $owner->ownedProjects()->create(['name' => 'Target workspace']);
        $project->members()->create(['customer_id' => $owner->id, 'role' => ProjectMember::ROLE_OWNER]);
        $this->vm($active, 'active-workspace-vm');

        $bundle = VmBundle::create([
            'name' => 'Compute', 'slug' => 'proforma-compute', 'cpu_cores' => 1, 'ram_gb' => 2,
            'disk_gb' => 30, 'ip_count' => 1, 'monthly_price' => 73000, 'hourly_price' => 100,
            'stopped_monthly_price' => 1000, 'is_active' => true,
        ]);
        ResourceRate::create(['resource' => ResourceRate::IP, 'label' => 'IP', 'unit' => 'IP', 'hourly_price' => 7, 'is_active' => true]);
        $stopped = $this->vm($project, 'stopped-vm', [
            'display_name' => 'Alpha',
            'status' => VirtualMachine::STATUS_STOPPED,
            'pricing_mode' => 'inherit',
            'vm_bundle_id' => $bundle->id,
        ]);
        $resource = $this->vm($project, 'resource-vm', ['display_name' => 'Alpha', 'pricing_mode' => 'resources', 'ip_count' => 2]);
        $suspended = $this->vm($project, 'Suspended', ['status' => VirtualMachine::STATUS_SUSPENDED]);
        $locked = $this->vm($project, 'Locked', ['password_reset_status' => 'pending']);
        $this->vm($project, 'DeletedStatus', ['status' => VirtualMachine::STATUS_DELETED]);
        $this->vm($project, 'DeletedTimestamp', ['deleted_at' => now()]);

        $url = self::CUSTOMER_URL.'/projects/'.$project->uuid.'/proforma';
        $expected = ['subtotal' => 83220, 'tax' => 0, 'total' => 83220];
        $response = $this->actingAs($owner, 'customer')->withSession([ProjectAccessService::SESSION_KEY => $active->id])
            ->get($url)->assertOk()->assertViewHas('totals', $expected);
        $this->assertSame(
            [$stopped->id, $resource->id, $locked->id, $suspended->id],
            $response->viewData('rows')->pluck('vm.id')->all(),
        );

        UsageAccrual::create([
            'customer_id' => $owner->id, 'project_id' => $project->id, 'scope_key' => 'project:'.$project->id,
            'category' => UsageAccrual::CATEGORY_NETWORK, 'resource_type' => 'network_usage_bucket',
            'resource_id' => $stopped->id, 'virtual_machine_id' => $stopped->id, 'service_date' => now()->toDateString(),
            'period_start' => now()->subHour(), 'period_end' => now(), 'amount' => 999000,
        ]);

        $this->get($url)->assertOk()
            ->assertSessionHas(ProjectAccessService::SESSION_KEY, $active->id)
            ->assertViewHas('totals', $expected)
            ->assertSee('Target workspace')->assertSee('Alpha')->assertSee('خاموش')->assertSee(self::TRAFFIC_NOTICE)
            ->assertDontSee('active-workspace-vm')->assertDontSee('DeletedStatus')->assertDontSee('DeletedTimestamp')
            ->assertDontSee($owner->email)->assertDontSee($owner->phone)
            ->assertDontSee('internal-hostname')->assertDontSee('192.0.2.4')
            ->assertDontSee('مالیات ارزش افزوده');

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('usage_accruals', 1);
    }

    public function test_vat_is_charged_once_and_currency_uses_the_customer_formatter(): void
    {
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        AppSetting::setValue(AppSetting::BILLING_CURRENCY, 'IRR');
        AppSetting::setValue(AppSetting::TAX_ENABLED, true, 'boolean');
        AppSetting::setValue(AppSetting::TAX_RATE_PERCENTAGE, 10.5, 'float');
        $this->vm($project, 'taxable', ['custom_monthly_price' => 101, 'tax_exempt' => false]);
        $this->vm($project, 'exempt', ['custom_monthly_price' => 203, 'tax_exempt' => true]);
        $url = self::CUSTOMER_URL.'/projects/'.$project->uuid.'/proforma';

        $this->actingAs($owner, 'customer')->get($url)
            ->assertOk()->assertViewHas('totals', ['subtotal' => 304, 'tax' => 11, 'total' => 315])
            ->assertSee('۱۰.۵٪')->assertSee('۳۱.۵ تومان');

        AppSetting::setValue(AppSetting::BILLING_CURRENCY, 'IRT');
        $this->get($url)->assertOk()->assertSee('۳۱۵ تومان');
    }

    public function test_empty_workspace_and_jalali_reference_month_use_customer_timezone(): void
    {
        config(['app.customer_timezone' => 'Asia/Tehran']);
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $url = self::CUSTOMER_URL.'/projects/'.$project->uuid.'/proforma';

        $this->travelTo(CarbonImmutable::parse('2026-10-22T20:29:00Z'));
        $this->actingAs($owner, 'customer')->get($url)
            ->assertOk()->assertViewHas('referenceMonth', 'مهر 1405')
            ->assertViewHas('periodEnd', '2026/10/22');

        $this->travelTo(CarbonImmutable::parse('2026-10-22T20:30:00Z'));
        $this->get($url)
            ->assertOk()->assertViewHas('referenceMonth', 'آبان 1405')
            ->assertViewHas('generatedAt', '1405/08/01 00:00')
            ->assertViewHas('periodStart', '2026/10/23')
            ->assertViewHas('periodEnd', '2026/11/21')
            ->assertViewHas('totals', ['subtotal' => 0, 'tax' => 0, 'total' => 0])
            ->assertSee('این فضای کاری ماشین مجازی برای درج در پیش‌فاکتور ندارد.')
            ->assertSee(self::TRAFFIC_NOTICE);
    }

    public function test_missing_provider_price_is_reported_and_suppresses_all_totals(): void
    {
        Exceptions::fake();
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $this->vm($project, 'Unavailable provider price', [
            'provider' => VirtualMachine::PROVIDER_HETZNER, 'pricing_mode' => 'inherit',
        ]);
        $this->vm($project, 'Available provider snapshot', [
            'provider' => VirtualMachine::PROVIDER_HETZNER, 'pricing_mode' => 'inherit',
            'provider_metadata' => ['hetzner_price' => ['monthly_price_irr' => 81000]],
        ]);

        $this->actingAs($owner, 'customer')->get(self::CUSTOMER_URL.'/projects/'.$project->uuid.'/proforma')
            ->assertOk()->assertViewHas('totals', null)
            ->assertSee('Unavailable provider price')->assertSee('قیمت در دسترس نیست')
            ->assertSee('Available provider snapshot')->assertSee('۸,۱۰۰ تومان')
            ->assertSee(self::TRAFFIC_NOTICE)->assertDontSee('جمع برآورد')
            ->assertDontSee('Hetzner price mapping');
        Exceptions::assertReported(VmPriceUnavailableException::class);
    }

    private function vm(Project $project, string $name, array $attributes = []): VirtualMachine
    {
        return $project->virtualMachines()->create([
            'customer_id' => $project->owner_customer_id, 'name' => $name,
            'cpu_cores' => 1, 'ram_gb' => 2, 'disk_gb' => 30, 'ip_count' => 1,
            'hostname' => 'internal-hostname', 'ip_address' => '192.0.2.4',
            'status' => VirtualMachine::STATUS_RUNNING, 'provisioning_status' => VirtualMachine::PROVISION_READY,
            'pricing_mode' => 'custom', 'custom_monthly_price' => 73000,
            ...$attributes,
        ]);
    }
}
