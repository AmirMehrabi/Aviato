<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Services\CloudDns\CloudDnsRollout;
use App\Services\CloudDns\CloudDnsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CloudDnsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_distinguishes_verification_publication_and_nameserver_connection(): void
    {
        config(['cloud-dns.enabled' => true]);
        AppSetting::setValue(CloudDnsRollout::KEY, ['mode' => 'all', 'customer_ids' => []], 'json', 'cloud-dns');
        Queue::fake();
        $customer = Customer::factory()->create();
        $zone = app(CloudDnsService::class)->create($customer->ensureDefaultProject(), 'example.ir', $customer);
        $this->actingAs($customer, 'customer');
        $url = 'https://cp.localhost/cloud-dns/'.$zone->uuid;
        $this->get($url)->assertOk()->assertSee('رکورد را اضافه کردم؛ بررسی مالکیت')->assertSee('اتصال هنوز بررسی نشده')->assertSee('دی‌ان‌اس ابری');
        $zone->update(['verified_at' => now(), 'status' => 'ready', 'synced_revision' => $zone->revision, 'delegation_status' => 'pending', 'checked_at' => now()]);
        $this->get($url)->assertOk()->assertDontSee('رکورد را اضافه کردم؛ بررسی مالکیت')->assertSee('نیم‌سرورها هنوز متصل نیستند')->assertDontSee('دامنه به آویاتو متصل است');
        $zone->update(['delegation_status' => 'active']);
        $this->get($url)->assertOk()->assertSee('دامنه به آویاتو متصل است');
        $zone->update(['status' => 'pending', 'revision' => $zone->revision + 1]);
        $this->get($url)->assertOk()->assertDontSee('دامنه به آویاتو متصل است')->assertSee('تغییرات در انتظار انتشار هستند');
    }

    public function test_record_validation_retains_editor_values_for_correction(): void
    {
        config(['cloud-dns.enabled' => true]);
        AppSetting::setValue(CloudDnsRollout::KEY, ['mode' => 'all', 'customer_ids' => []], 'json', 'cloud-dns');
        Queue::fake();
        $customer = Customer::factory()->create();
        $zone = app(CloudDnsService::class)->create($customer->ensureDefaultProject(), 'example.ir', $customer);
        $url = 'https://cp.localhost/cloud-dns/'.$zone->uuid;
        $this->actingAs($customer, 'customer')->from($url)->post($url.'/records', [
            'name' => '@', 'type' => 'MX', 'ttl' => 300, 'content' => '10 invalid host', 'editor_mode' => 'edit',
        ])->assertRedirect($url)->assertSessionHasErrors('content')->assertSessionHasInput('content', '10 invalid host')->assertSessionHasInput('editor_mode', 'edit');
        $this->get($url)->assertOk()->assertSee('dns-editor-title')->assertSee('10 invalid host');
        $this->assertDatabaseCount('dns_record_sets', 0);
    }
}
