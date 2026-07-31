<?php

namespace Tests\Feature;

use App\Models\AdminTablePreference;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTablePreferencesTest extends TestCase
{
    use RefreshDatabase;

    private string $adminBaseUrl = 'https://admin.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portals.admin.domain' => 'admin.localhost',
            'portals.customer.domain' => 'cp.localhost',
        ]);
    }

    public function test_admin_can_save_and_reset_a_table_sort_preference(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->putJson($this->adminBaseUrl.'/table-preferences/customers', [
                'column' => 'name',
                'direction' => 'desc',
            ])
            ->assertOk()
            ->assertJsonPath('data.column', 'name')
            ->assertJsonPath('data.direction', 'desc');

        $this->assertDatabaseHas('admin_table_preferences', [
            'user_id' => $admin->id,
            'table_key' => 'customers',
            'sort_column' => 'name',
            'sort_direction' => 'desc',
        ]);

        $this->deleteJson($this->adminBaseUrl.'/table-preferences/customers')
            ->assertNoContent();

        $this->assertDatabaseMissing('admin_table_preferences', [
            'user_id' => $admin->id,
            'table_key' => 'customers',
        ]);
    }

    public function test_preference_endpoint_rejects_unknown_tables(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin');

        $this->putJson($this->adminBaseUrl.'/table-preferences/not-a-table', [
            'column' => 'name',
            'direction' => 'asc',
        ])->assertNotFound();
    }

    public function test_preference_endpoint_rejects_unknown_columns(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin');

        $this->putJson($this->adminBaseUrl.'/table-preferences/customers', [
            'column' => 'password',
            'direction' => 'asc',
        ])->assertUnprocessable();
    }

    public function test_preference_endpoint_rejects_unknown_directions(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin');

        $this->putJson($this->adminBaseUrl.'/table-preferences/customers', [
            'column' => 'name',
            'direction' => 'sideways',
        ])->assertUnprocessable();
    }

    public function test_preferences_are_isolated_per_admin_and_table(): void
    {
        $firstAdmin = User::factory()->create();
        $secondAdmin = User::factory()->create();

        AdminTablePreference::create([
            'user_id' => $firstAdmin->id,
            'table_key' => 'customers',
            'sort_column' => 'name',
            'sort_direction' => 'desc',
        ]);

        $this->actingAs($secondAdmin, 'admin')
            ->putJson($this->adminBaseUrl.'/table-preferences/projects', [
                'column' => 'created_at',
                'direction' => 'asc',
            ])
            ->assertOk();

        $this->assertDatabaseCount('admin_table_preferences', 2);
        $this->assertDatabaseHas('admin_table_preferences', [
            'user_id' => $firstAdmin->id,
            'table_key' => 'customers',
            'sort_column' => 'name',
            'sort_direction' => 'desc',
        ]);
        $this->assertDatabaseHas('admin_table_preferences', [
            'user_id' => $secondAdmin->id,
            'table_key' => 'projects',
            'sort_column' => 'created_at',
            'sort_direction' => 'asc',
        ]);
    }

    public function test_customer_index_uses_saved_preference_and_query_string_can_override_it(): void
    {
        $admin = User::factory()->create();
        Customer::factory()->create(['name' => 'آلفا مشتری']);
        Customer::factory()->create(['name' => 'یاقوت مشتری']);

        AdminTablePreference::create([
            'user_id' => $admin->id,
            'table_key' => 'customers',
            'sort_column' => 'name',
            'sort_direction' => 'desc',
        ]);

        $this->actingAs($admin, 'admin')
            ->get($this->adminBaseUrl.'/customers')
            ->assertOk()
            ->assertSeeInOrder(['یاقوت مشتری', 'آلفا مشتری']);

        $this->get($this->adminBaseUrl.'/customers?sort=name&direction=asc')
            ->assertOk()
            ->assertSeeInOrder(['آلفا مشتری', 'یاقوت مشتری']);
    }
}
