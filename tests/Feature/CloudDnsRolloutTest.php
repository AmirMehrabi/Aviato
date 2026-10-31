<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\User;
use App\Services\CloudDns\CloudDnsRollout;
use App\Services\CloudDns\CloudDnsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CloudDnsRolloutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cloud-dns.enabled' => true]);
        Queue::fake();
        Http::fake(['*' => Http::response(['version' => '5.0.2'])]);
    }

    private function rollout(string $mode, array $ids = []): void
    {
        AppSetting::setValue(CloudDnsRollout::KEY, ['mode' => $mode, 'customer_ids' => $ids], 'json', 'cloud-dns');
    }

    public function test_rollout_defaults_to_no_customer_access_and_hidden_navigation(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')->get('https://cp.localhost/cloud-dns')->assertNotFound();
        $this->get('https://cp.localhost/profile')->assertOk()->assertDontSee('href="/cloud-dns"', false);
        $this->post('https://cp.localhost/cloud-dns', ['name' => 'example.ir'])->assertNotFound();
        $this->assertDatabaseCount('dns_zones', 0);
    }

    public function test_selected_customer_can_access_but_others_cannot_use_any_zone_endpoint(): void
    {
        $pilot = Customer::factory()->create();
        $other = Customer::factory()->create();
        $zone = app(CloudDnsService::class)->create($other->ensureDefaultProject(), 'example.ir', $other);
        $this->rollout('selected', [$pilot->id]);
        $this->actingAs($pilot, 'customer')->get('https://cp.localhost/cloud-dns')->assertOk();
        $this->get('https://cp.localhost/profile')->assertOk()->assertSee('href="/cloud-dns"', false);
        $this->actingAs($other, 'customer');
        $url = 'https://cp.localhost/cloud-dns/'.$zone->uuid;
        foreach (['', '/export'] as $suffix) {
            $this->get($url.$suffix)->assertNotFound();
        }
        foreach (['verify', 'check', 'records', 'import'] as $action) {
            $this->post($url.'/'.$action)->assertNotFound();
        }
        $this->delete($url)->assertNotFound();
        $this->delete($url.'/records/1')->assertNotFound();
        $this->get('https://my.aviato.ir/cloud-dns')->assertNotFound();
    }

    public function test_allowlisted_member_cannot_bypass_non_allowlisted_project_owner(): void
    {
        $owner = Customer::factory()->create();
        $member = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $project->members()->create(['customer_id' => $member->id, 'role' => 'admin']);
        $this->rollout('selected', [$member->id]);
        $this->actingAs($member, 'customer')->withSession(['customer.active_project_id' => $project->id])
            ->get('https://cp.localhost/cloud-dns')->assertNotFound();
        $this->rollout('selected', [$owner->id]);
        $this->get('https://cp.localhost/cloud-dns')->assertNotFound();
        $this->rollout('selected', [$owner->id, $member->id]);
        $this->get('https://cp.localhost/cloud-dns')->assertOk();
    }

    public function test_selected_mode_still_requires_project_dns_permissions(): void
    {
        $owner = Customer::factory()->create();
        $member = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $project->members()->create(['customer_id' => $member->id, 'role' => 'member']);
        $this->rollout('selected', [$owner->id, $member->id]);
        $this->actingAs($owner, 'customer')->get('https://cp.localhost/cloud-dns')->assertOk();
        $this->actingAs($member, 'customer')->withSession(['customer.active_project_id' => $project->id])
            ->get('https://cp.localhost/cloud-dns')->assertNotFound();
    }

    public function test_revocation_is_immediate_and_does_not_remove_zones_or_disable_admins(): void
    {
        $owner = Customer::factory()->create();
        $zone = app(CloudDnsService::class)->create($owner->ensureDefaultProject(), 'example.ir', $owner);
        $zone->update(['verified_at' => now(), 'status' => 'ready']);
        $this->rollout('selected', [$owner->id]);
        $this->actingAs($owner, 'customer')->get('https://cp.localhost/cloud-dns')->assertOk();
        app(CloudDnsRollout::class)->update('remove', null, $owner->id);
        $this->get('https://cp.localhost/cloud-dns')->assertNotFound();
        $this->rollout('disabled', [$owner->id]);
        $this->get('https://cp.localhost/cloud-dns')->assertNotFound();
        $this->assertDatabaseHas('dns_zones', ['id' => $zone->id, 'name' => 'example.ir', 'status' => 'ready', 'delete_requested_at' => null]);
        Queue::assertNothingPushed();
        $this->actingAs(User::factory()->create(['role' => AdminRole::Admin]), 'admin')
            ->get('https://admin.localhost/cloud-dns/'.$zone->uuid)->assertOk();
    }

    public function test_admin_can_add_remove_and_change_mode_without_losing_the_allowlist(): void
    {
        $pilot = Customer::factory()->create(['phone' => '09120000001']);
        $this->actingAs(User::factory()->create(['role' => AdminRole::Admin]), 'admin');
        $url = 'https://admin.localhost/cloud-dns/rollout';
        $this->post($url, ['action' => 'add', 'identifier' => $pilot->phone])->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame(['mode' => 'selected', 'customer_ids' => [$pilot->id]], app(CloudDnsRollout::class)->configuration());
        $response = $this->get('https://admin.localhost/cloud-dns')->assertOk()->assertSee($pilot->phone)->assertSee('انتشار تدریجی مدیریت DNS');
        if ($path = getenv('CLOUD_DNS_ROLLOUT_RENDER_PATH')) {
            file_put_contents($path, $response->getContent());
        }
        $this->post($url, ['action' => 'mode', 'mode' => 'disabled'])->assertSessionHasNoErrors();
        $this->assertSame(['mode' => 'disabled', 'customer_ids' => [$pilot->id]], app(CloudDnsRollout::class)->configuration());
        $this->post($url, ['action' => 'mode', 'mode' => 'selected'])->assertSessionHasNoErrors();
        $this->post($url, ['action' => 'remove', 'customer_id' => $pilot->id])->assertSessionHasNoErrors();
        $this->assertSame(['mode' => 'selected', 'customer_ids' => []], app(CloudDnsRollout::class)->configuration());
        $this->assertDatabaseHas('admin_audit_logs', ['event' => 'admin.cloud-dns.rollout.update', 'result' => 'success']);
    }

    public function test_infrastructure_and_dns_record_admins_cannot_grant_rollout_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => AdminRole::Infrastructure]), 'admin')
            ->post('https://admin.localhost/cloud-dns/rollout', ['action' => 'mode', 'mode' => 'all'])->assertForbidden();
        $this->get('https://admin.localhost/cloud-dns')->assertOk()->assertDontSee('name="identifier"', false);
        $this->actingAs(User::factory()->create(['role' => AdminRole::Custom, 'permissions' => ['dns.read', 'dns.rollout.manage']]), 'admin')
            ->post('https://admin.localhost/cloud-dns/rollout', ['action' => 'mode', 'mode' => 'disabled'])->assertSessionHasNoErrors();
    }

    public function test_invalid_rollout_updates_do_not_change_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => AdminRole::Admin]), 'admin');
        $url = 'https://admin.localhost/cloud-dns/rollout';
        $this->post($url, ['action' => 'mode', 'mode' => 'typo'])->assertSessionHasErrors('mode');
        $this->post($url, ['action' => 'mode', 'mode' => 'all'])->assertSessionHasErrors('mode');
        $this->rollout('all');
        $this->assertSame('selected', app(CloudDnsRollout::class)->configuration()['mode']);
        $this->post($url, ['action' => 'add', 'identifier' => 'missing@example.ir'])->assertSessionHasErrors('identifier');
        $this->assertSame(['mode' => 'selected', 'customer_ids' => []], app(CloudDnsRollout::class)->configuration());
    }
}
