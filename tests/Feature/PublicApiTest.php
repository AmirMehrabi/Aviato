<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_and_revoke_an_api_key(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer');
        $response = $this->post('https://cp.localhost/profile/api-tokens', ['name' => 'Monitoring']);

        $response->assertRedirect();
        $response->assertSessionHas('api_token');
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $customer->id, 'name' => 'Monitoring']);

        $token = $customer->tokens()->firstOrFail();
        $this->delete('https://cp.localhost/profile/api-tokens/'.$token->id)->assertRedirect();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_api_can_read_project_wallet_and_transactions(): void
    {
        $customer = Customer::factory()->create();
        $project = $customer->ensureDefaultProject();
        $token = $customer->createToken('Test client', ['wallet:read'])->plainTextToken;

        app(WalletService::class)->credit($customer, 250000, 'Test credit', metadata: ['project_id' => $project->id]);

        $response = $this->withToken($token)->getJson('/api/v1/projects/'.$project->uuid.'/wallet');
        $response->assertOk()->assertJsonPath('data.balance', 250000)->assertHeader('X-Request-Id');

        $response = $this->withToken($token)->getJson('/api/v1/projects/'.$project->uuid.'/wallet/transactions');
        $response->assertOk()->assertJsonPath('data.0.description', 'Test credit');
        $this->assertDatabaseHas('api_request_logs', ['customer_id' => $customer->id, 'status_code' => 200]);
    }

    public function test_api_rejects_another_project_and_invalid_query(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $token = $customer->createToken('Test client', ['wallet:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/projects/'.$other->ensureDefaultProject()->uuid.'/wallet')
            ->assertForbidden()->assertJsonPath('error.code', 'project_forbidden');

        $this->withToken($token)->getJson('/api/v1/projects/'.$customer->ensureDefaultProject()->uuid.'/wallet/transactions?per_page=101')
            ->assertUnprocessable()->assertJsonPath('error.code', 'validation_error');
    }

    public function test_api_requires_a_bearer_token_and_logs_the_failure(): void
    {
        $project = Customer::factory()->create()->ensureDefaultProject();

        $this->getJson('/api/v1/projects/'.$project->uuid.'/wallet')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');

        $this->assertDatabaseHas('api_request_logs', ['status_code' => 401, 'failure_type' => 'authentication']);
    }

    public function test_public_api_documentation_is_available_to_guests(): void
    {
        $this->get('/api-docs')->assertOk()->assertSee('AVIATO API')->assertSee('/projects/{project_uuid}/wallet');
    }
}
