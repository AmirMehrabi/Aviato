<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerStorageManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_can_load_the_storage_page(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/storage')
            ->assertOk()
            ->assertSee('فضای ذخیره‌سازی S3');
    }

    public function test_customer_can_create_a_bucket_and_access_key_for_the_active_project(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->post($this->customerBaseUrl.'/storage/buckets', ['name' => 'app-backups'])
            ->assertSessionHas('status');

        $project = $customer->ensureDefaultProject();
        $this->assertDatabaseHas('storage_buckets', [
            'project_id' => $project->id,
            'name' => 'app-backups',
        ]);

        $response = $this->from($this->customerBaseUrl.'/storage')
            ->post($this->customerBaseUrl.'/storage/access-keys', ['description' => 'Production backups']);

        $response->assertRedirect($this->customerBaseUrl.'/storage')
            ->assertSessionHas('storage_credentials.access_key_id')
            ->assertSessionHas('storage_credentials.secret');

        $this->assertDatabaseHas('storage_access_keys', [
            'project_id' => $project->id,
            'description' => 'Production backups',
            'status' => 'active',
        ]);
    }

    public function test_customer_cannot_use_another_projects_bucket_for_deletion(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $bucket = $otherCustomer->ensureDefaultProject()->storageBuckets()->create(['name' => 'other-project-files']);

        $this->actingAs($customer, 'customer');

        $this->delete($this->customerBaseUrl.'/storage/buckets/'.$bucket->id)->assertNotFound();
        $this->assertDatabaseHas('storage_buckets', ['id' => $bucket->id]);
    }

    public function test_bucket_name_must_follow_s3_style_rules(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->from($this->customerBaseUrl.'/storage')
            ->post($this->customerBaseUrl.'/storage/buckets', ['name' => 'Not Valid'])
            ->assertSessionHasErrors('name');
    }
}
