<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Jobs\SyncDnsZone;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\DnsZone;
use App\Models\User;
use App\Services\CloudDns\CloudDnsRollout;
use App\Services\CloudDns\CloudDnsService;
use App\Services\CloudDns\DnsLookup;
use App\Services\CloudDns\RecordValidator;
use App\Services\CloudDns\ZoneFile;
use App\Services\CloudDns\ZoneSynchronizer;
use App\Services\ProjectLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CloudDnsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cloud-dns.nodes.primary.url' => 'https://5.202.19.14:8443', 'cloud-dns.nodes.secondary.url' => 'https://5.202.19.15:8443', 'cloud-dns.enabled' => true, 'cloud-dns.nodes.primary.key' => 'test-primary', 'cloud-dns.nodes.secondary.key' => 'test-secondary']);
        AppSetting::setValue(CloudDnsRollout::KEY, ['mode' => 'all', 'customer_ids' => []], 'json', 'cloud-dns');
        Queue::fake();
        Http::preventStrayRequests();
    }

    private function zone(Customer $customer, string $name = 'example.ir'): DnsZone
    {
        return app(CloudDnsService::class)->create($customer->ensureDefaultProject(), $name, $customer);
    }

    public function test_owner_can_create_and_render_a_zone_without_provisioning_unverified_dns(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')->post('https://cp.localhost/cloud-dns', ['name' => 'Example.IR.'])->assertRedirect();
        $zone = DnsZone::firstOrFail();
        $this->assertSame('example.ir', $zone->name);
        $this->get('https://cp.localhost/cloud-dns')->assertOk()->assertSee('example.ir');
        $this->get('https://cp.localhost/cloud-dns/'.$zone->uuid)->assertOk()->assertSee($zone->verification_token)->assertSee('ns2.aviato.ir');
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_other_projects_cannot_read_modify_export_or_delete_a_zone(): void
    {
        $zone = $this->zone(Customer::factory()->create());
        $this->actingAs(Customer::factory()->create(), 'customer');
        $url = 'https://cp.localhost/cloud-dns/'.$zone->uuid;
        $this->get($url)->assertNotFound();
        $this->get($url.'/export')->assertNotFound();
        $this->post($url.'/records', ['type' => 'A', 'name' => '@', 'ttl' => 300, 'content' => '192.0.2.1'])->assertNotFound();
        $this->post($url.'/verify')->assertNotFound();
        $this->delete($url)->assertNotFound();
    }

    public function test_dns_permissions_are_independent_from_vm_permissions(): void
    {
        $owner = Customer::factory()->create();
        $member = Customer::factory()->create();
        $zone = $this->zone($owner);
        $membership = $owner->ensureDefaultProject()->members()->create(['customer_id' => $member->id, 'role' => 'member', 'dns_read' => false, 'dns_manage' => false]);
        $this->actingAs($member, 'customer')->withSession(['customer.active_project_id' => $zone->project_id]);
        $url = 'https://cp.localhost/cloud-dns/'.$zone->uuid;
        $this->get($url)->assertNotFound();
        $membership->update(['dns_read' => true]);
        $this->get($url)->assertOk();
        $this->post($url.'/records', ['type' => 'A', 'name' => '@', 'ttl' => 300, 'content' => '192.0.2.1'])->assertNotFound();
        $membership->update(['dns_manage' => true]);
        $this->post($url.'/records', ['type' => 'A', 'name' => '@', 'ttl' => 300, 'content' => '192.0.2.1'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('dns_record_sets', 1);
    }

    public function test_only_unique_txt_proof_enables_provisioning(): void
    {
        $owner = Customer::factory()->create();
        $zone = $this->zone($owner);
        $this->mock(DnsLookup::class, function ($mock) use ($zone): void {
            $mock->shouldReceive('txt')->once()->with('_aviato-verification.example.ir')->andReturn([$zone->verification_token]);
        });
        $this->actingAs($owner, 'customer')->post('https://cp.localhost/cloud-dns/'.$zone->uuid.'/verify')->assertSessionHasNoErrors();
        $this->assertNotNull($zone->fresh()->verified_at);
        Queue::assertPushed(SyncDnsZone::class);
    }

    public function test_missing_txt_and_existing_delegation_do_not_prove_ownership(): void
    {
        $owner = Customer::factory()->create();
        $zone = $this->zone($owner);
        $this->mock(DnsLookup::class, fn ($mock) => $mock->shouldReceive('txt')->once()->andReturn(['unrelated-token']));
        $this->actingAs($owner, 'customer')->post('https://cp.localhost/cloud-dns/'.$zone->uuid.'/verify')->assertSessionHasErrors('verification');
        Queue::assertNothingPushed();
    }

    public function test_record_changes_are_validated_atomic_and_audited(): void
    {
        $owner = Customer::factory()->create();
        $zone = $this->zone($owner);
        $dns = app(CloudDnsService::class);
        $dns->saveRecords($zone, [['name' => 'www', 'type' => 'A', 'ttl' => 300, 'content' => "192.0.2.1\n192.0.2.2"]], $owner);
        $this->assertSame(['192.0.2.1', '192.0.2.2'], $zone->recordSets()->first()->records);
        $this->assertSame('records.updated', $zone->changes()->latest('id')->first()->action);
        try {
            $dns->saveRecords($zone, [['name' => 'www', 'type' => 'CNAME', 'ttl' => 300, 'content' => 'target.example.net.']], $owner);
            $this->fail('CNAME conflict accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('type', $e->errors());
        }
        $this->assertDatabaseCount('dns_record_sets', 1);
    }

    public function test_invalid_records_and_out_of_zone_names_are_rejected(): void
    {
        $validator = app(RecordValidator::class);
        $invalid = [
            ['@', 'CNAME', 'target.example.net.'], ['@', 'NS', 'ns3.example.net.'],
            ['other.ir.', 'A', '192.0.2.1'], ['@', 'A', 'not-an-ip'],
            ['mail', 'MX', '99999 mail.example.ir.'], ['_sip._tcp', 'SRV', '1 2 99999 sip.example.ir.'],
            ['@', 'CAA', '300 issue "letsencrypt.org"'], ['www', 'CNAME', "a.ir.\nb.ir."],
        ];
        foreach ($invalid as [$name, $type, $content]) {
            try {
                $validator->rrset('example.ir', compact('name', 'type', 'content') + ['ttl' => 300]);
                $this->fail('Invalid record accepted: '.$type.' '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_import_export_preserves_multivalue_txt_and_relative_targets(): void
    {
        $owner = Customer::factory()->create();
        $zone = $this->zone($owner);
        $file = app(ZoneFile::class);
        $text = '$ORIGIN example.ir.'."\n".'$TTL 300'."\n@ IN MX 10 mail\n@ IN TXT (\"v=spf1 \"\n \"-all\") ; comment\nwww IN A 192.0.2.1\n    IN A 192.0.2.2\n";
        app(CloudDnsService::class)->saveRecords($zone, $file->parse($zone->name, $text), $owner);
        $this->assertSame(['10 mail.example.ir.'], $zone->recordSets()->where('type', 'MX')->first()->records);
        $this->assertSame(['"v=spf1 -all"'], $zone->recordSets()->where('type', 'TXT')->first()->records);
        $roundtrip = $file->parse($zone->name, $file->export($zone));
        $this->assertCount(3, $roundtrip);
        $this->assertSame(2, count($zone->recordSets()->where('type', 'A')->first()->records));
    }

    public function test_import_rejects_unsupported_types_without_partial_changes(): void
    {
        $this->expectException(ValidationException::class);
        app(ZoneFile::class)->parse('example.ir', "@ 300 IN A 192.0.2.1\n@ 300 IN HTTPS 1 . alpn=\"h2\"");
    }

    public function test_expired_unverified_claims_can_be_reclaimed_but_verified_domains_cannot(): void
    {
        $owner = Customer::factory()->create();
        $zone = $this->zone($owner);
        $zone->update(['verification_expires_at' => now()->subMinute()]);
        $second = Customer::factory()->create();
        $replacement = $this->zone($second);
        $this->assertSame($second->ensureDefaultProject()->id, $replacement->project_id);
        $replacement->update(['verified_at' => now()]);
        $this->expectException(ValidationException::class);
        $this->zone($owner);
    }

    public function test_project_and_customer_deletion_are_blocked_while_dns_is_live(): void
    {
        $owner = Customer::factory()->create();
        $zone = $this->zone($owner);
        $this->assertArrayHasKey('dns', app(ProjectLifecycleService::class)->deletionBlockers($owner->ensureDefaultProject(), $owner));
        $admin = User::factory()->create(['role' => AdminRole::Admin]);
        $this->actingAs($admin, 'admin')->delete('https://admin.localhost/customers/'.$owner->id)->assertStatus(409);
    }

    public function test_read_only_admin_cannot_change_or_delete_dns(): void
    {
        $zone = $this->zone(Customer::factory()->create());
        $admin = User::factory()->create(['role' => AdminRole::Custom, 'permissions' => ['dns.read']]);
        Http::fake(['*' => Http::response(['version' => '5.0.2'])]);
        $this->actingAs($admin, 'admin')->get('https://admin.localhost/cloud-dns')->assertOk();
        $this->get('https://admin.localhost/cloud-dns/'.$zone->uuid)->assertOk();
        $this->delete('https://admin.localhost/cloud-dns/'.$zone->uuid)->assertForbidden();
        $this->post('https://admin.localhost/cloud-dns/'.$zone->uuid.'/retry')->assertForbidden();
    }

    public function test_delete_retains_claim_until_both_nodes_confirm_removal(): void
    {
        $owner = Customer::factory()->create();
        $zone = $this->zone($owner);
        $zone->update(['verified_at' => now(), 'status' => 'ready']);
        app(CloudDnsService::class)->remove($zone, $owner);
        $this->assertSame('example.ir', $zone->fresh()->name);
        $fail = true;
        Http::fake(function ($request) use (&$fail) {
            return Http::response('', $fail && str_contains($request->url(), '5.202.19.14') ? 503 : 204);
        });
        try {
            app(ZoneSynchronizer::class)->sync($zone->id);
            $this->fail('Partial delete was accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame('deleting', $zone->fresh()->status);
        }
        $fail = false;
        app(ZoneSynchronizer::class)->sync($zone->id);
        $this->assertSame('deleted', $zone->fresh()->status);
        $this->assertNotSame('example.ir', $zone->fresh()->name);
    }

    public function test_suspension_retries_keep_the_zone_suspended(): void
    {
        $owner = Customer::factory()->create();
        $zone = $this->zone($owner);
        $zone->update(['verified_at' => now(), 'status' => 'suspended', 'revision' => 3]);
        Http::fake(['*' => Http::response('', 204)]);
        app(ZoneSynchronizer::class)->sync($zone->id);
        $this->assertSame('suspended', $zone->fresh()->status);
        $this->assertSame(3, $zone->fresh()->synced_revision);
        Http::assertSentCount(2);
    }

    public function test_plan_limits_do_not_depend_on_wallet_balance(): void
    {
        $owner = Customer::factory()->create();
        config(['cloud-dns.plans.free.zone_limit' => 1, 'cloud-dns.plans.free.record_limit' => 1]);
        $zone = $this->zone($owner);
        try {
            app(CloudDnsService::class)->saveRecords($zone, [['name' => '@', 'type' => 'A', 'ttl' => 300, 'content' => "192.0.2.1\n192.0.2.2"]], $owner);
            $this->fail('Record quota exceeded');
        } catch (ValidationException $e) {
            $this->assertDatabaseCount('dns_record_sets', 0);
        }
        $this->expectException(ValidationException::class);
        $this->zone($owner, 'another.ir');
    }

    public function test_sync_is_idempotent_and_only_ready_after_secondary_serial_matches(): void
    {
        $owner = Customer::factory()->create();
        $zone = $this->zone($owner);
        $zone->update(['verified_at' => now(), 'status' => 'pending', 'revision' => 1]);
        app(CloudDnsService::class)->saveRecords($zone, [['name' => '@', 'type' => 'A', 'ttl' => 300, 'content' => '192.0.2.7']], $owner);
        $primary = ['serial' => 100, 'rrsets' => [
            ['name' => 'example.ir.', 'type' => 'SOA', 'ttl' => 3600, 'records' => [['content' => 'ns1.aviato.ir. hostmaster.aviato.ir. 100 3600 600 1209600 300', 'disabled' => false]]],
            ['name' => 'example.ir.', 'type' => 'NS', 'ttl' => 3600, 'records' => [['content' => 'ns1.aviato.ir.', 'disabled' => false], ['content' => 'ns2.aviato.ir.', 'disabled' => false]]],
        ]];
        $caughtUp = false;
        $patches = 0;
        Http::fake(function ($request) use (&$primary, &$caughtUp, &$patches) {
            if ($request->method() === 'GET') {
                $result = $primary;
                if (str_contains($request->url(), '5.202.19.15') && ! $caughtUp) {
                    $result['serial'] = 99;
                }

                return Http::response($result);
            }
            if ($request->method() === 'PATCH') {
                $patches++;
                foreach ($request['rrsets'] as $record) {
                    unset($record['changetype']);
                    $primary['rrsets'][] = $record;
                }
                $primary['serial']++;
            }

            return Http::response('', 204);
        });
        try {
            app(ZoneSynchronizer::class)->sync($zone->id);
            $this->fail('Zone marked ready before secondary transfer');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('secondary transfer', $e->getMessage());
            $this->assertSame('pending', $zone->fresh()->status);
        }
        $caughtUp = true;
        app(ZoneSynchronizer::class)->sync($zone->id);
        $this->assertSame(1, $patches);
        $this->assertSame('ready', $zone->fresh()->status);
        $this->assertSame($zone->fresh()->revision, $zone->fresh()->synced_revision);
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && ($request['slave_tsig_key_ids'] ?? []) === ['aviato-transfer.']);
    }

    public function test_customer_can_render_records_and_export_desired_configuration(): void
    {
        $owner = Customer::factory()->create();
        $zone = $this->zone($owner);
        app(CloudDnsService::class)->saveRecords($zone, [
            ['name' => '@', 'type' => 'A', 'ttl' => 300, 'content' => '192.0.2.7'],
            ['name' => 'www', 'type' => 'CNAME', 'ttl' => 300, 'content' => 'example.ir.'],
            ['name' => '@', 'type' => 'MX', 'ttl' => 300, 'content' => '10 mail.example.ir.'],
            ['name' => '@', 'type' => 'TXT', 'ttl' => 300, 'content' => 'v=spf1 mx -all'],
        ], $owner);
        $this->actingAs($owner, 'customer');
        $response = $this->get('https://cp.localhost/cloud-dns/'.$zone->uuid);
        $response->assertOk()->assertSee('192.0.2.7')->assertSee('dns-editor-title');
        if ($path = getenv('CLOUD_DNS_RENDER_PATH')) {
            file_put_contents($path, $response->getContent());
        }
        $this->get('https://cp.localhost/cloud-dns/'.$zone->uuid.'/export')->assertOk()->assertSee('example.ir. 300 IN A 192.0.2.7');
    }

    public function test_txt_escaping_empty_values_and_long_dkim_content_roundtrip(): void
    {
        $validator = app(RecordValidator::class);
        $this->assertSame('"hello world"', $validator->content('TXT', '"hello\\032world"'));
        $this->assertSame('""', $validator->content('TXT', '""'));
        $long = 'v=DKIM1; p='.str_repeat('A', 600);
        $encoded = $validator->content('TXT', $long);
        $this->assertSame($encoded, $validator->content('TXT', $encoded));
        $this->assertStringContainsString('" "', $encoded);
    }
}
