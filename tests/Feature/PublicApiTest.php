<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProjectMember;
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
        $this->withToken($token)->getJson('/api/v1/projects/'.$project->uuid.'/wallet/transactions/'.$response->json('data.0.id'))
            ->assertOk()
            ->assertJsonPath('data.description', 'Test credit')
            ->assertJsonPath('data.id', $response->json('data.0.id'));
        $this->assertDatabaseHas('api_request_logs', ['customer_id' => $customer->id, 'status_code' => 200]);
    }

    public function test_api_transaction_detail_respects_project_and_owner_wallet_visibility(): void
    {
        $customer = Customer::factory()->create();
        $project = $customer->ensureDefaultProject();
        $otherProject = $customer->ownedProjects()->create(['name' => 'Other Project']);
        $otherProject->members()->create(['customer_id' => $customer->id, 'role' => ProjectMember::ROLE_OWNER]);
        $otherCustomer = Customer::factory()->create();
        $token = $customer->createToken('Test client', ['wallet:read'])->plainTextToken;
        $wallets = app(WalletService::class);

        $ownerTransaction = $wallets->credit($customer, 100000, 'Owner-level credit');
        $projectTransaction = $wallets->charge($customer, 10000, 'Project charge', metadata: ['project_id' => $project->id]);
        $otherProjectTransaction = $wallets->charge($customer, 5000, 'Other project charge', metadata: ['project_id' => $otherProject->id]);
        $otherCustomerTransaction = $wallets->credit($otherCustomer, 1000, 'Another customer');

        $this->withToken($token)->getJson('/api/v1/projects/'.$project->uuid.'/wallet/transactions/'.$ownerTransaction->id)
            ->assertOk()
            ->assertJsonPath('data.description', 'Owner-level credit');
        $this->withToken($token)->getJson('/api/v1/projects/'.$project->uuid.'/wallet/transactions/'.$projectTransaction->id)
            ->assertOk()
            ->assertJsonPath('data.description', 'Project charge');
        $this->withToken($token)->getJson('/api/v1/projects/'.$project->uuid.'/wallet/transactions/'.$otherProjectTransaction->id)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'transaction_not_found');
        $this->withToken($token)->getJson('/api/v1/projects/'.$project->uuid.'/wallet/transactions/'.$otherCustomerTransaction->id)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'transaction_not_found');
        $this->withToken($token)->getJson('/api/v1/projects/'.$project->uuid.'/wallet/transactions/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'transaction_not_found');
        $this->withToken($token)->getJson('/api/v1/projects/'.$project->uuid.'/wallet/transactions/not-a-number')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'transaction_not_found');
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
        $this->get('/api-docs')
            ->assertOk()
            ->assertSee('AVIATO API')
            ->assertSee('/projects/{project_uuid}/wallet')
            ->assertSee('/projects/{project_uuid}/wallet/transactions')
            ->assertSee('/projects/{project_uuid}/wallet/transactions/{transaction}')
            ->assertSee('YOUR_PROJECT_UUID')
            ->assertSee('Get remaining balance')
            ->assertSee('List transactions')
            ->assertSee('Get one transaction');
    }
}
