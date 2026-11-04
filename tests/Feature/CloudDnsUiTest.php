<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Customer;
use App\Models\User;
use App\Services\CloudDns\CloudDnsRollout;
use App\Services\CloudDns\CloudDnsService;
use App\Services\CloudDns\RecordValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CloudDnsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_distinguishes_verification_publication_and_nameserver_connection(): void
    {
        config(['cloud-dns.enabled' => true]);
        Queue::fake();
        $customer = Customer::factory()->create();
        app(CloudDnsRollout::class)->update('add', null, $customer->id);
        $zone = app(CloudDnsService::class)->create($customer->ensureDefaultProject(), 'example.ir', $customer);
        $this->actingAs($customer, 'customer');
        $url = 'https://cp.localhost/cloud-dns/'.$zone->uuid;
        $initial = $this->get($url)->assertOk()->assertSee('بررسی اتصال و فعال‌سازی')->assertSee('اتصال هنوز بررسی نشده')->assertSee('مدیریت DNS');
        if ($path = getenv('CLOUD_DNS_UX_RENDER_PATH')) {
            file_put_contents($path, $initial->getContent());
        }
        $this->assertLessThan(strpos($initial->getContent(), 'id="dns-records"'), strpos($initial->getContent(), 'id="domain-nameservers"'));
        $zone->update(['verified_at' => now(), 'status' => 'ready', 'synced_revision' => $zone->revision, 'delegation_status' => 'pending', 'checked_at' => now()]);
        $pending = $this->get($url)->assertOk()->assertDontSee('بررسی اتصال و فعال‌سازی')->assertSee('نیم‌سرورها هنوز متصل نیستند')->assertDontSee('دامنه به آویاتو متصل است');
        $this->assertLessThan(strpos($pending->getContent(), 'id="domain-nameservers"'), strpos($pending->getContent(), 'id="dns-records"'));
        $zone->update(['delegation_status' => 'active']);
        $html = $this->get($url)->assertOk()->assertSee('دامنه به آویاتو متصل است')->getContent();
        $this->assertLessThan(strpos($html, 'id="domain-nameservers"'), strpos($html, 'id="dns-records"'));
        $this->assertStringContainsString('<details id="dns-connection-details" class=', $html);
        $zone->update(['status' => 'pending', 'revision' => $zone->revision + 1]);
        $this->get($url)->assertOk()->assertDontSee('دامنه به آویاتو متصل است')->assertSee('تغییرات در انتظار انتشار هستند');
    }

    public function test_management_page_contains_history_and_enforces_zone_ownership(): void
    {
        config(['cloud-dns.enabled' => true]);
        Queue::fake();
        $customer = Customer::factory()->create();
        app(CloudDnsRollout::class)->update('add', null, $customer->id);
        $zone = app(CloudDnsService::class)->create($customer->ensureDefaultProject(), 'example.ir', $customer);
        $url = 'https://cp.localhost/cloud-dns/'.$zone->uuid;
        $this->actingAs($customer, 'customer')->get($url)->assertOk()
            ->assertDontSee('تاریخچه تغییرات')->assertDontSee('حذف دامنه از هر دو سرور')
            ->assertSee('/cloud-dns/'.$zone->uuid.'/management', false)->assertViewHas('changes', null);
        $this->get($url.'/management')->assertOk()->assertSee('تاریخچه تغییرات')
            ->assertSee('حذف دامنه از هر دو سرور')->assertDontSee('id="dns-records"', false);
        $other = Customer::factory()->create();
        app(CloudDnsRollout::class)->update('add', null, $other->id);
        $other->ensureDefaultProject();
        $this->actingAs($other, 'customer')->get($url.'/management')->assertNotFound();
    }

    public function test_record_validation_retains_editor_values_for_correction(): void
    {
        config(['cloud-dns.enabled' => true]);
        Queue::fake();
        $customer = Customer::factory()->create();
        app(CloudDnsRollout::class)->update('add', null, $customer->id);
        $zone = app(CloudDnsService::class)->create($customer->ensureDefaultProject(), 'example.ir', $customer);
        $url = 'https://cp.localhost/cloud-dns/'.$zone->uuid;
        $this->actingAs($customer, 'customer')->from($url)->post($url.'/records', [
            'name' => '@', 'type' => 'MX', 'ttl' => 300, 'content' => '10 invalid host', 'editor_mode' => 'edit',
        ])->assertRedirect($url)->assertSessionHasErrors('content')->assertSessionHasInput('content', '10 invalid host')->assertSessionHasInput('editor_mode', 'edit');
        $response = $this->get($url)->assertOk()->assertSee('dns-editor-title')->assertSee('10 invalid host');
        $this->assertStringNotContainsString('<li>مقدار رکورد MX معتبر نیست.</li>', $response->getContent());
        $this->assertStringNotContainsString('text-red-800">مقدار رکورد MX معتبر نیست.</div>', $response->getContent());
        $this->assertDatabaseCount('dns_record_sets', 0);
    }

    public function test_preview_is_read_only_and_matches_import_with_duplicates_conflicts_and_proxy_warning(): void
    {
        config(['cloud-dns.enabled' => true]);
        Queue::fake();
        $customer = Customer::factory()->create();
        app(CloudDnsRollout::class)->update('add', null, $customer->id);
        $dns = app(CloudDnsService::class);
        $zone = $dns->create($customer->ensureDefaultProject(), 'example.ir', $customer);
        $dns->saveRecords($zone, [['name' => '@', 'type' => 'A', 'ttl' => 600, 'content' => '192.0.2.1']], $customer);
        $url = 'https://cp.localhost/cloud-dns/'.$zone->uuid;
        $text = "@ 300 IN A 192.0.2.1\n@ 300 IN A 192.0.2.2 ; cf-proxied:true\n@ 300 IN CNAME other.example.\nwww 300 IN A invalid\n";
        $revision = $zone->fresh()->revision;
        $auditCount = $zone->changes()->count();
        $this->actingAs($customer, 'customer');
        $report = $this->postJson($url.'/import/preview', ['zone_file' => $text])->assertOk()
            ->assertJsonPath('imported', 1)->assertJsonPath('duplicates', 1)->json();
        $this->assertCount(3, $report['warnings']);
        $this->assertSame($revision, $zone->fresh()->revision);
        $this->assertSame($auditCount, $zone->changes()->count());
        $this->assertSame(['192.0.2.1'], $zone->recordSets()->first()->records);
        Queue::assertNothingPushed();
        $this->from($url)->post($url.'/import', ['zone_file' => $text])->assertSessionHas('dns_import', $report);
        $this->assertSame(['192.0.2.1', '192.0.2.2'], $zone->recordSets()->first()->records);
        $this->assertSame(600, $zone->recordSets()->first()->ttl);
        $other = Customer::factory()->create();
        app(CloudDnsRollout::class)->update('add', null, $other->id);
        $this->actingAs($other, 'customer')->postJson($url.'/import/preview', ['zone_file' => $text])->assertNotFound();
        $this->getJson($url.'/status')->assertNotFound();
        $this->post($url.'/verification/renew')->assertNotFound();
    }

    public function test_txt_renewal_endpoint_is_removed_and_records_are_retained(): void
    {
        config(['cloud-dns.enabled' => true]);
        Queue::fake();
        $customer = Customer::factory()->create();
        app(CloudDnsRollout::class)->update('add', null, $customer->id);
        $dns = app(CloudDnsService::class);
        $zone = $dns->create($customer->ensureDefaultProject(), 'example.ir', $customer);
        $dns->saveRecords($zone, [['name' => 'blog.example.ir', 'type' => 'NS', 'ttl' => 300, 'content' => 'ns1.example.net']], $customer);
        $url = 'https://cp.localhost/cloud-dns/'.$zone->uuid;
        $this->actingAs($customer, 'customer')->post($url.'/verification/renew')->assertNotFound();
        $this->assertSame('blog.example.ir.', $zone->recordSets()->first()->name);
        Queue::assertNothingPushed();
    }

    public function test_status_never_claims_connection_from_publication_alone(): void
    {
        config(['cloud-dns.enabled' => true]);
        Queue::fake();
        $customer = Customer::factory()->create();
        app(CloudDnsRollout::class)->update('add', null, $customer->id);
        $zone = app(CloudDnsService::class)->create($customer->ensureDefaultProject(), 'example.ir', $customer);
        $zone->update(['verified_at' => now(), 'status' => 'ready', 'synced_revision' => $zone->revision]);
        $url = 'https://cp.localhost/cloud-dns/'.$zone->uuid.'/status';
        $this->actingAs($customer, 'customer')->getJson($url)->assertOk()->assertJsonPath('published', true)->assertJsonPath('healthy', false);
        $zone->update(['delegation_status' => 'active']);
        $this->getJson($url)->assertOk()->assertJsonPath('healthy', true);
        $zone->update(['revision' => $zone->revision + 1]);
        $this->getJson($url)->assertOk()->assertJsonPath('published', false)->assertJsonPath('healthy', false);
    }

    public function test_read_only_dns_member_can_poll_but_cannot_preview_or_use_removed_renewal_route(): void
    {
        config(['cloud-dns.enabled' => true]);
        Queue::fake();
        $owner = Customer::factory()->create();
        $member = Customer::factory()->create();
        app(CloudDnsRollout::class)->update('mode', 'all', null);
        $project = $owner->ensureDefaultProject();
        $project->members()->create(['customer_id' => $member->id, 'role' => 'member', 'dns_read' => true]);
        $zone = app(CloudDnsService::class)->create($project, 'example.ir', $owner);
        $url = 'https://cp.localhost/cloud-dns/'.$zone->uuid;
        $this->actingAs($member, 'customer')->withSession(['customer.active_project_id' => $project->id]);
        $this->getJson($url.'/status')->assertOk();
        $this->postJson($url.'/import/preview', ['zone_file' => '@ 300 IN A 192.0.2.1'])->assertNotFound();
        $this->post($url.'/verification/renew')->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => AdminRole::Custom, 'permissions' => ['dns.read']]), 'admin');
        $this->getJson('https://admin.localhost/cloud-dns/'.$zone->uuid.'/status')->assertOk();
        $this->postJson('https://admin.localhost/cloud-dns/'.$zone->uuid.'/import/preview', ['zone_file' => '@ 300 IN A 192.0.2.1'])->assertForbidden();
    }

    public function test_ns_record_names_accept_short_and_full_forms_and_reject_root_names(): void
    {
        $validator = app(RecordValidator::class);
        foreach (['blog', 'blog.example.ir', 'BLOG.EXAMPLE.IR', 'blog.example.ir.'] as $name) {
            $record = $validator->rrset('example.ir', ['name' => $name, 'type' => 'NS', 'ttl' => 300, 'content' => 'ns1.example.net']);
            $this->assertSame('blog.example.ir.', $record['name']);
        }
        foreach (['@', 'example.ir', 'EXAMPLE.IR.'] as $name) {
            try {
                $validator->rrset('example.ir', ['name' => $name, 'type' => 'NS', 'ttl' => 300, 'content' => 'ns1.example.net']);
                $this->fail('Root nameservers must remain managed by Aviato.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('name', $exception->errors());
            }
        }
    }
}
