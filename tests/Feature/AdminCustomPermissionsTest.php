<?php

namespace Tests\Feature;

use App\Enums\AdminAbility;
use App\Enums\AdminRole;
use App\Http\Middleware\AuthorizeAdminRoute;
use App\Models\AdminAuditLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Notifications\TicketDatabaseNotification;
use App\Services\AdminDashboardActions;
use App\Services\AdminDashboardFinance;
use App\Services\WalletService;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminCustomPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private string $base = 'https://admin.localhost';

    private function custom(array $permissions): User
    {
        return User::factory()->create(['role' => AdminRole::Custom, 'permissions' => AdminAccess::normalizePermissions($permissions)]);
    }

    public function test_admin_can_save_custom_access_and_dependencies_and_remove_it(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');
        $this->get($this->base.'/users/create')->assertOk()->assertSee('permissions[]', false);
        $this->post($this->base.'/users', [
            'name' => 'Custom operator', 'email' => 'custom@example.com', 'role' => 'custom', 'is_active' => 1,
            'password' => 'Temporary-Password-123', 'password_confirmation' => 'Temporary-Password-123',
            'permissions' => ['tickets.manage', 'virtual-machines.power'],
        ])->assertRedirect();
        $user = User::where('email', 'custom@example.com')->firstOrFail();
        $this->assertTrue($user->allows('tickets.read'));
        $this->assertTrue($user->allows('virtual-machines.power'));
        $this->assertFalse($user->allows('virtual-machines.console'));
        $this->get($this->base.'/users/'.$user->id.'/edit')->assertOk()->assertSee('دسترسی ماژول‌ها');
        DB::table('admin_session_users')->insert(['session_id' => 'permission-test-session', 'user_id' => $user->id]);
        $this->put($this->base.'/users/'.$user->id, [
            'name' => $user->name, 'email' => $user->email, 'role' => 'custom', 'is_active' => 1, 'permissions' => [],
        ])->assertRedirect();
        $this->assertSame([], $user->refresh()->permissions);
        $this->assertDatabaseMissing('admin_session_users', ['session_id' => 'permission-test-session']);
        $audit = AdminAuditLog::where('route_name', 'admin.users.update')->latest('id')->firstOrFail();
        $this->assertArrayHasKey('permissions', $audit->changes);
    }

    public function test_unknown_and_privilege_escalating_permissions_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');
        $user = $this->custom([]);
        foreach (['unknown.permission', 'users.manage'] as $permission) {
            $this->put($this->base.'/users/'.$user->id, [
                'name' => $user->name, 'email' => $user->email, 'role' => 'custom', 'is_active' => 1,
                'permissions' => [$permission],
            ])->assertSessionHasErrors('permissions.0');
        }
        $this->actingAs($this->custom(array_column(AdminAbility::cases(), 'value')), 'admin');
        $this->get($this->base.'/users')->assertForbidden();
        $this->post($this->base.'/users', [])->assertForbidden();
    }

    public function test_read_access_does_not_grant_mutations_or_sensitive_vm_operations(): void
    {
        $customer = Customer::factory()->create();
        $vm = VirtualMachine::create(['customer_id' => $customer->id, 'name' => 'Restricted VM', 'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40, 'ip_count' => 1, 'status' => VirtualMachine::STATUS_STOPPED]);
        $this->actingAs($this->custom(['customers.read', 'virtual-machines.read', 'tickets.read']), 'admin');
        $this->get($this->base.'/customers')->assertOk()->assertDontSee('موجودی فعلی کیف پول');
        $this->get($this->base.'/virtual-machines')->assertOk()->assertDontSee('VM جدید')->assertDontSee('هزینه ماهانه');
        $this->get($this->base.'/tickets')->assertOk();
        $this->get($this->base.'/virtual-machines/'.$vm->uuid)->assertOk()
            ->assertDontSee('اطلاعات مالی')->assertDontSee('هزینه تخمینی ماهانه')
            ->assertDontSee('form-start')->assertDontSee('form-delete');
        foreach (['start', 'stop', 'retry-provisioning', 'console/session', 'transfer'] as $action) {
            $this->post($this->base.'/virtual-machines/'.$vm->uuid.'/'.$action)->assertForbidden();
        }
        $this->delete($this->base.'/virtual-machines/'.$vm->uuid)->assertForbidden();
        $this->get($this->base.'/virtual-machines/'.$vm->uuid.'/edit')->assertForbidden();
        $this->post($this->base.'/customers/'.$customer->id.'/wallet-transactions', [])->assertForbidden();
        $this->post($this->base.'/customers/'.$customer->id.'/impersonate', [])->assertForbidden();
        $this->get($this->base.'/billing')->assertForbidden();
        $this->get($this->base.'/api/proxmox-servers')->assertForbidden();
    }

    public function test_financial_records_and_counts_are_excluded_from_custom_dashboard_queries(): void
    {
        $customer = Customer::factory()->create(['name' => 'Private financial customer']);
        Payment::create(['wallet_id' => $customer->wallet->id, 'type' => Payment::TYPE_TOP_UP, 'customer_id' => $customer->id, 'provider' => 'dummy', 'amount' => 123456789, 'currency' => 'IRR', 'status' => Payment::STATUS_PENDING, 'created_at' => now()->subHours(3)]);
        app(WalletService::class)->walletFor($customer)->update(['balance' => -123456789]);
        $ticket = Ticket::create(['customer_id' => $customer->id, 'number' => 'PERMISSION-1', 'subject' => 'Visible support task', 'status' => Ticket::STATUS_OPEN, 'priority' => Ticket::PRIORITY_NORMAL]);
        $this->actingAs($this->custom(['dashboard.view', 'tickets.read']), 'admin');
        $this->mock(AdminDashboardFinance::class, fn ($mock) => $mock->makePartial()->shouldNotReceive('snapshot'));
        $response = $this->get($this->base.'/dashboard')->assertOk()
            ->assertSee($ticket->subject)->assertDontSee('123456789')
            ->assertDontSee('پرداخت نیازمند بررسی')->assertDontSee('کیف پول منفی')
            ->assertViewHas('finance', null)
            ->assertViewHas('dashboard', fn ($d) => $d['total_count'] === 1 && count($d['health']) === 1 && $d['health'][0]['category'] === 'tickets');
        $this->get($this->base.'/dashboard?category=payments&page=2')->assertOk()
            ->assertViewHas('dashboard', fn ($d) => $d['total_count'] === 0 && $d['items']->isEmpty());
        $this->actingAs(User::factory()->create(), 'admin');
        $key = app(AdminDashboardActions::class)->snapshot(collect())['items']->firstWhere('category', 'کیف پول منفی')['key'];
        $this->actingAs($this->custom(['dashboard.view', 'tickets.read']), 'admin');
        $this->post($this->base.'/dashboard/warnings/dismiss', ['warning_key' => $key])->assertRedirect();
        $this->assertDatabaseMissing('admin_dashboard_warning_dismissals', ['warning_key' => $key]);
    }

    public function test_finance_permission_shows_records_without_granting_wallet_changes_or_exports(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($this->custom(['dashboard.view', 'billing.read', 'customers.read']), 'admin');
        $this->get($this->base.'/dashboard')->assertOk()->assertViewHas('finance', fn ($finance) => is_array($finance));
        $this->get($this->base.'/customers/'.$customer->id)->assertOk()->assertSee('کیف پول مشتری')->assertDontSee('ثبت تراکنش');
        $this->get($this->base.'/billing')->assertOk()->assertDontSee('خروجی CSV');
        $this->get($this->base.'/billing/exports/payments')->assertForbidden();
        $this->post($this->base.'/customers/'.$customer->id.'/wallet-transactions')->assertForbidden();
    }

    public function test_customer_and_workspace_pages_hide_finances_for_nonfinancial_users(): void
    {
        $customer = Customer::factory()->create();
        $project = $customer->ensureDefaultProject();
        $this->actingAs($this->custom(['customers.read', 'projects.read']), 'admin');
        $this->get($this->base.'/customers/'.$customer->id)->assertOk()
            ->assertDontSee('کیف پول مشتری')->assertDontSee('آخرین تراکنش‌های کیف پول')
            ->assertDontSee('صورتحساب‌ها')->assertViewHas('financial', null);
        $this->get($this->base.'/workspaces/'.$project->uuid)->assertOk()
            ->assertDontSee('هزینه ماهانه')->assertDontSee('پیش فاکتور')
            ->assertDontSee('ارسال دستی اعلان موجودی');
        $this->get($this->base.'/workspaces/'.$project->uuid.'/proforma')->assertForbidden();
        $this->patch($this->base.'/workspaces/'.$project->uuid, ['name' => 'Unauthorized'])->assertForbidden();
    }

    public function test_search_and_notifications_are_filtered_after_access_is_removed(): void
    {
        $customer = Customer::factory()->create(['name' => 'Search secret']);
        $user = $this->custom(['customers.read', 'tickets.read']);
        $notification = $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => TicketDatabaseNotification::class, 'data' => ['title' => 'Private ticket', 'body' => 'Hidden ticket contents']]);
        $this->actingAs($user, 'admin');
        $this->getJson($this->base.'/search?q=Search')->assertOk()->assertJsonPath('groups.0.items.0.title', $customer->name);
        $this->getJson($this->base.'/notifications')->assertJsonPath('unread_count', 1);
        $user->update(['permissions' => []]);
        $this->getJson($this->base.'/search?q=Search')->assertExactJson(['groups' => []]);
        $this->getJson($this->base.'/notifications')->assertExactJson(['items' => [], 'unread_count' => 0, 'ticket_unread_count' => 0]);
        $this->postJson($this->base.'/notifications/'.$notification->id.'/read')->assertNotFound();
    }

    public function test_custom_users_login_to_an_allowed_page_including_profile_only_accounts(): void
    {
        $user = $this->custom([]);
        $user->update(['password' => 'Temporary-Password-123']);
        $this->post($this->base.'/login', ['login' => $user->email, 'password' => 'Temporary-Password-123'])
            ->assertRedirect($this->base.'/profile');
        $this->get($this->base.'/')->assertRedirect($this->base.'/profile');
        $this->get($this->base.'/profile')->assertOk();
        $this->get($this->base.'/dashboard')->assertForbidden();
    }

    public function test_infrastructure_modules_can_be_granted_independently(): void
    {
        $this->actingAs($this->custom(['hetzner.read']), 'admin');
        $this->get($this->base.'/hetzner-accounts')->assertOk();
        $this->get($this->base.'/proxmox-servers')->assertForbidden();
        $this->get($this->base.'/cloud-images')->assertForbidden();
        $this->get($this->base.'/ip-pools')->assertForbidden();
        $this->actingAs($this->custom(['infrastructure.read']), 'admin');
        $this->get($this->base.'/proxmox-servers')->assertOk();
        $this->get($this->base.'/hetzner-accounts')->assertForbidden();
        $this->get($this->base.'/virtual-machines')->assertForbidden();
        $this->actingAs($this->custom(['network.read']), 'admin');
        $this->get($this->base.'/billing/network')->assertOk()->assertDontSee('هزینه محاسبه‌شده');
        $this->get($this->base.'/ip-pools')->assertForbidden();
    }

    public function test_vm_power_console_transfer_and_delete_are_independent_grants(): void
    {
        foreach ([
            'virtual-machines.power' => ['admin.virtual-machines.start', 'POST'],
            'virtual-machines.console' => ['admin.virtual-machines.console.session', 'POST'],
            'virtual-machines.transfer' => ['admin.virtual-machines.transfer', 'POST'],
            'virtual-machines.delete' => ['admin.virtual-machines.destroy', 'DELETE'],
        ] as $permission => [$route, $method]) {
            $user = $this->custom([$permission]);
            $this->assertTrue(AdminAccess::allowsRoute($user, $route, $method));
            $this->assertTrue(AdminAccess::allowsRoute($user, 'admin.virtual-machines.show'));
            $this->assertFalse(AdminAccess::allowsRoute($user, 'admin.virtual-machines.edit'));
            foreach (['virtual-machines.power', 'virtual-machines.console', 'virtual-machines.transfer', 'virtual-machines.delete'] as $other) {
                $this->assertSame($permission === $other, $user->allows($other));
            }
        }
    }

    public function test_customer_edit_permission_cannot_bypass_suspend_permission(): void
    {
        $customer = Customer::factory()->create(['status' => 'active']);
        $this->actingAs($this->custom(['customers.manage']), 'admin');
        $this->put($this->base.'/customers/'.$customer->id, [
            'name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone,
            'status' => 'suspended',
        ])->assertForbidden();
        $this->assertSame('active', $customer->refresh()->status);
        $this->put($this->base.'/customers/'.$customer->id, [
            'name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone,
            'status' => 'active', 'password' => 'Changed-Password-123', 'password_confirmation' => 'Changed-Password-123',
        ])->assertForbidden();
        $this->delete($this->base.'/customers/'.$customer->id)->assertForbidden();
        $this->patch($this->base.'/customers/'.$customer->id.'/suspend')->assertForbidden();
    }

    public function test_restricted_audit_viewer_cannot_access_financial_audit_records(): void
    {
        $record = AdminAuditLog::create([
            'event' => 'Financial secret event', 'method' => 'POST', 'route_name' => 'admin.customers.wallet-transactions.store',
            'path' => 'customers/1/wallet-transactions', 'result' => 'success', 'request_id' => (string) Str::uuid(),
            'metadata' => ['input' => ['amount' => 123456789]],
        ]);
        $this->actingAs($this->custom(['audit.view', 'customers.read']), 'admin');
        $this->get($this->base.'/audit-logs')->assertOk()->assertDontSee('Financial secret event');
        $this->get($this->base.'/audit-logs/'.$record->id)->assertForbidden();
    }

    public function test_vm_transfer_page_hides_financial_values_and_snapshots(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $user = $this->custom(['virtual-machines.transfer']);
        $vm = VirtualMachine::create(['customer_id' => $customer->id, 'name' => 'Transfer VM', 'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40, 'ip_count' => 1, 'status' => VirtualMachine::STATUS_STOPPED, 'unbilled_amount' => 123456789]);
        $vm->transfers()->create([
            'from_customer_id' => $customer->id, 'to_customer_id' => $other->id, 'initiated_by_user_id' => $user->id,
            'status' => 'completed', 'unbilled_amount_transferred' => 123456789,
            'snapshot_before' => ['vm_name' => 'Transfer VM', 'unbilled_amount' => 123456789, 'monthly_cost_estimate' => 987654321],
        ]);
        $this->actingAs($user, 'admin');
        $this->get($this->base.'/virtual-machines/'.$vm->uuid.'/transfer')->assertOk()
            ->assertDontSee('123456789')->assertDontSee('987654321')->assertDontSee('هزینه ماهانه:')
            ->assertDontSee('مبلغ انتقال:')->assertSee('[RESTRICTED]');
    }

    public function test_every_protected_module_route_requires_explicit_permissions_and_unknown_routes_deny_access(): void
    {
        $none = $this->custom([]);
        $all = $this->custom(array_column(AdminAbility::cases(), 'value'));
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            if (! in_array('admin.route-access', $route->gatherMiddleware(), true)) {
                continue;
            }
            $name = $route->getName();
            if (preg_match('/^admin\.(profile\.|notifications\.|table-preferences\.|search$)/', $name)) {
                continue;
            }
            $count++;
            $this->assertNotEmpty(AdminAccess::routeAbilities($name, $route->methods()[0]), $name.' must have a permission mapping');
            $request = Request::create('/', $route->methods()[0]);
            $request->setRouteResolver(fn () => $route);
            $request->setUserResolver(fn () => $none);
            try {
                app(AuthorizeAdminRoute::class)->handle($request, fn () => response('allowed'));
                $this->fail($name.' must deny an unprivileged user');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode(), $name);
            }
            $this->assertSame(! str_starts_with($name, 'admin.users.'), AdminAccess::allowsRoute($all, $name, $route->methods()[0]), $name);
        }
        $this->assertGreaterThan(150, $count);
        $this->assertFalse(AdminAccess::allowsRoute($all, 'admin.future-module.index'));
    }
}
