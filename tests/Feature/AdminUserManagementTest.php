<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminAuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $base = 'https://admin.localhost';

    public function test_admin_can_create_update_reset_and_deactivate_panel_user(): void
    {
        $admin = User::factory()->create(['role' => AdminRole::Admin]);
        $this->actingAs($admin, 'admin');

        $this->post($this->base.'/users', [
            'name' => 'Finance User', 'email' => 'finance@example.com', 'phone' => null,
            'role' => AdminRole::Accountant->value, 'is_active' => 1,
            'password' => 'Temporary-Password-123', 'password_confirmation' => 'Temporary-Password-123',
        ])->assertRedirect();

        $user = User::query()->where('email', 'finance@example.com')->firstOrFail();
        $this->assertSame(AdminRole::Accountant, $user->role);
        $this->assertTrue($user->is_active);
        $this->get($this->base.'/users/'.$user->id)->assertOk()->assertSee('Finance User');

        $this->put($this->base.'/users/'.$user->id, [
            'name' => 'Support User', 'email' => $user->email, 'phone' => null,
            'role' => AdminRole::Support->value, 'is_active' => 0,
        ])->assertRedirect();
        $this->assertSame(AdminRole::Support, $user->refresh()->role);
        $this->assertFalse($user->is_active);

        $this->post($this->base.'/users/'.$user->id.'/reset-password', [
            'password' => 'Another-Password-123', 'password_confirmation' => 'Another-Password-123',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('Another-Password-123', $user->refresh()->password));

        $audit = AdminAuditLog::query()->where('route_name', 'admin.users.reset-password')->latest('id')->firstOrFail();
        $this->assertSame('[REDACTED]', $audit->metadata['input']['password']);
    }

    public function test_fixed_roles_enforce_read_and_write_boundaries(): void
    {
        $customer = Customer::factory()->create();
        $accountant = User::factory()->create(['role' => AdminRole::Accountant]);

        $this->actingAs($accountant, 'admin')
            ->get($this->base.'/billing')->assertOk();
        $this->get($this->base.'/customers/'.$customer->id)->assertOk();
        $this->post($this->base.'/customers/'.$customer->id.'/wallet-transactions', [
            'type' => 'credit', 'amount' => 1000, 'description' => 'Forbidden credit',
        ])->assertForbidden();
        $this->get($this->base.'/users')->assertForbidden();

        $support = User::factory()->create(['role' => AdminRole::Support]);
        $this->actingAs($support, 'admin')->get($this->base.'/tickets')->assertOk();
        $this->get($this->base.'/customers/'.$customer->id)->assertOk();
        $this->get($this->base.'/billing')->assertForbidden();

        $infrastructure = User::factory()->create(['role' => AdminRole::Infrastructure]);
        $this->actingAs($infrastructure, 'admin')->get($this->base.'/virtual-machines')->assertOk();
        $this->get($this->base.'/billing/network')->assertOk();
        $this->get($this->base.'/billing/payments')->assertForbidden();

        $this->assertDatabaseHas('admin_audit_logs', ['actor_user_id' => $accountant->id, 'result' => 'denied']);
    }

    public function test_inactive_user_cannot_login_or_continue_an_existing_session(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com', 'phone' => null, 'is_active' => false,
            'password' => 'Temporary-Password-123',
        ]);

        $this->post($this->base.'/login', ['login' => $user->email, 'password' => 'Temporary-Password-123'])
            ->assertSessionHasErrors('login');
        $this->assertGuest('admin');
        $this->assertDatabaseHas('admin_audit_logs', ['actor_user_id' => $user->id, 'event' => 'admin.login', 'result' => 'denied']);

        $this->actingAs($user, 'admin')->get($this->base.'/billing')->assertRedirect($this->base.'/login');
        $this->assertGuest('admin');
    }

    public function test_last_active_admin_and_self_are_protected(): void
    {
        $admin = User::factory()->create(['role' => AdminRole::Admin]);
        $this->actingAs($admin, 'admin');

        $this->put($this->base.'/users/'.$admin->id, [
            'name' => $admin->name, 'email' => $admin->email, 'phone' => $admin->phone,
            'role' => AdminRole::Accountant->value, 'is_active' => 1,
        ])->assertStatus(422);
        $this->delete($this->base.'/users/'.$admin->id)->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => AdminRole::Admin->value, 'is_active' => true]);
    }

    public function test_role_specific_dashboard_redirects_to_primary_workspace(): void
    {
        $accountant = User::factory()->create(['role' => AdminRole::Accountant]);
        $this->actingAs($accountant, 'admin')->get($this->base.'/dashboard')->assertRedirect($this->base.'/billing');

        $support = User::factory()->create(['role' => AdminRole::Support]);
        $this->actingAs($support, 'admin')->get($this->base.'/dashboard')->assertRedirect($this->base.'/tickets');

        $infrastructure = User::factory()->create(['role' => AdminRole::Infrastructure]);
        $this->actingAs($infrastructure, 'admin')->get($this->base.'/dashboard')->assertRedirect($this->base.'/virtual-machines');
    }
}
