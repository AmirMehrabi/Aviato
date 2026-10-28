<?php

namespace Tests\Feature;

use App\Jobs\DeliverCorporateNotification;
use App\Models\AppSetting;
use App\Models\BillingIdentity;
use App\Models\CorporateDelivery;
use App\Models\CorporateProfile;
use App\Models\CorporateRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Corporate\BillingIdentityService;
use App\Services\Corporate\CorporateVerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;
use Tests\TestCase;

class CorporateVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.'.random_int(1, 250).'.'.random_int(1, 250)]);
        config(['portals.admin.domain' => 'admin.localhost', 'portals.customer.domain' => 'cp.localhost']);
        AppSetting::setValue('corporate.enabled', true, 'boolean', 'corporate');
        AppSetting::setValue(AppSetting::NATIONAL_CODE_VERIFICATION_TOKEN, 'test-token', 'string', 'customer');
        Cache::flush();
        Queue::fake();
        Storage::fake('local');
        Http::fake(['service.zohal.io/*' => Http::response(['result' => 1, 'response_body' => ['data' => $this->company()]])]);
    }

    private function company(): array
    {
        return ['national_id' => '10123456789', 'name' => 'شرکت نمونه', 'register_number' => '12345', 'address' => 'تهران', 'postal_code' => '1234567890', 'activity_end_date' => null];
    }

    private function customer(): Customer
    {
        return Customer::factory()->create(['national_code_verified_at' => now(), 'national_code' => '0100000002', 'phone' => '0912'.str_pad((string) (Customer::count() + 1), 7, '0', STR_PAD_LEFT)]);
    }

    private function draft(?Customer $customer = null): CorporateRequest
    {
        return app(CorporateVerificationService::class)->start($customer ?? $this->customer(), '10123456789');
    }

    private function submit(CorporateRequest $draft): void
    {
        app(CorporateVerificationService::class)->submit($draft, ['version' => $draft->version, 'representative_role' => 'signatory', 'billing_email' => 'billing@example.com', 'company_phone' => '02112345678', 'address' => 'تهران', 'postal_code' => '1234567890'],
            ['incorporation' => UploadedFile::fake()->create('incorporation.pdf', 10, 'application/pdf'), 'signatories' => UploadedFile::fake()->create('signatories.pdf', 10, 'application/pdf')]);
        $draft->refresh();
    }

    public function test_customer_can_lookup_submit_and_view_corporate_request(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer')->post('https://cp.localhost/profile/corporate', ['national_id' => '۱۰۱۲۳۴۵۶۷۸۹'])->assertRedirect();
        $draft = CorporateRequest::firstOrFail();
        Http::assertSent(fn ($request) => $request->url() === 'https://service.zohal.io/api/v0/services/inquiry/company_inquiry' && $request['national_id'] === '10123456789' && $request->hasHeader('Authorization', 'Bearer test-token'));
        $this->get('https://cp.localhost/profile/corporate/'.$draft->id)->assertOk()->assertSee('شرکت نمونه');
        $this->post('https://cp.localhost/profile/corporate/'.$draft->id, ['version' => 0, 'representative_role' => 'delegate', 'billing_email' => 'billing@example.com', 'company_phone' => '02112345678', 'address' => 'تهران', 'postal_code' => '۱۲۳۴۵۶۷۸۹۰', 'consent' => 1,
            'incorporation' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'signatories' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'), 'authorization' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $draft->fresh()->status);
        $this->assertDatabaseCount('corporate_documents', 3);
        Queue::assertPushed(DeliverCorporateNotification::class);
    }

    public function test_documents_and_requests_are_private_to_the_customer(): void
    {
        $draft = $this->draft();
        $this->submit($draft);
        $document = $draft->documents()->first();
        $this->actingAs($this->customer(), 'customer')->get('https://cp.localhost/profile/corporate/'.$draft->id)->assertNotFound();
        $this->get('https://cp.localhost/profile/corporate-documents/'.$document->id)->assertNotFound();
        $this->actingAs($draft->customer, 'customer')->get('https://cp.localhost/profile/corporate-documents/'.$document->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_review_requires_permissions_and_stale_reviews_are_rejected(): void
    {
        $draft = $this->draft();
        $this->submit($draft);
        $reader = User::factory()->create(['role' => 'custom', 'permissions' => ['corporate.read']]);
        $page = $this->actingAs($reader, 'admin')->get('https://admin.localhost/corporate-requests/'.$draft->id)->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//a[contains(@href, "/corporate-requests") and @aria-current="page"]')->length);
        $page->assertDontSee('مانده کیف پول هنگام ثبت رویداد');
        $this->patch('https://admin.localhost/corporate-requests/'.$draft->id, ['version' => 1, 'decision' => 'approved'])->assertForbidden();
        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin')->patch('https://admin.localhost/corporate-requests/'.$draft->id, ['version' => 0, 'decision' => 'approved'])->assertSessionHasErrors('version');
        $this->patch('https://admin.localhost/corporate-requests/'.$draft->id, ['version' => 1, 'decision' => 'needs_correction'])->assertSessionHasErrors('reason');
        $this->patch('https://admin.localhost/corporate-requests/'.$draft->id, ['version' => 1, 'decision' => 'needs_correction', 'reason' => 'آگهی جدید لازم است', 'internal_notes' => 'private-review-note'])->assertSessionHasNoErrors();
        $this->actingAs($draft->customer, 'customer')->get('https://cp.localhost/profile/corporate/'.$draft->id)->assertSee('آگهی جدید لازم است')->assertDontSee('private-review-note');
        $draft->refresh();
        app(CorporateVerificationService::class)->submit($draft, ['version' => 1, 'representative_role' => 'signatory', 'billing_email' => 'billing@example.com', 'company_phone' => '02112345678', 'address' => 'تهران', 'postal_code' => '1234567890'], []);
        $this->assertSame(2, $draft->fresh()->version);
        $this->assertDatabaseCount('corporate_documents', 4);
        $this->assertDatabaseCount('corporate_request_events', 3);
    }

    public function test_approval_keeps_historical_billing_and_schedules_next_jalali_month(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 28)->setTime(12, 0));
        $customer = $this->customer();
        $draft = $this->draft($customer);
        $this->submit($draft);
        $invoice = Invoice::create(['customer_id' => $customer->id, 'kind' => 'usage', 'number' => 'OLD-1', 'status' => 'issued', 'period_start' => now()->subMonth(), 'period_end' => now()->subDay(), 'issued_at' => now(), 'currency' => 'IRR', 'subtotal_amount' => 100, 'total_amount' => 100, 'wallet_charged_amount' => 100]);
        $before = $invoice->billingRecipient();
        app(CorporateVerificationService::class)->review($draft, User::factory()->create(), ['version' => 1, 'decision' => 'approved']);
        $profile = CorporateProfile::firstOrFail();
        $this->assertSame('1405/09/01', Jalalian::fromCarbon($profile->billing_effective_at->timezone('Asia/Tehran'))->format('Y/m/d'));
        $billing = app(BillingIdentityService::class);
        $this->assertSame('individual', $billing->at($customer, $profile->billing_effective_at->copy()->subSecond())['type']);
        $this->assertSame('corporate', $billing->at($customer, $profile->billing_effective_at)['type']);
        $this->assertSame($before, $invoice->fresh()->billingRecipient());
        $late = Invoice::create(['customer_id' => $customer->id, 'kind' => 'usage', 'number' => 'LATE-1', 'billing_key' => 'late', 'status' => 'issued', 'period_start' => now()->subMonth(), 'period_end' => now()->subDay(), 'issued_at' => $profile->billing_effective_at->copy()->addMonth(), 'currency' => 'IRR', 'subtotal_amount' => 100, 'total_amount' => 100, 'wallet_charged_amount' => 100]);
        $this->assertSame('individual', $late->billingRecipient()['type']);
        $this->assertDatabaseCount('billing_identities', 2);
        try {
            app(CorporateVerificationService::class)->review($draft, User::factory()->create(), ['version' => 1, 'decision' => 'approved']);
            $this->fail('Duplicate approval accepted');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('billing_identities', 2);
    }

    public function test_sms_uses_lookup_template_request_number_and_records_provider_id(): void
    {
        AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, 'test-key', 'string', 'kavenegar');
        AppSetting::setValue('sms.gateway', 'kavenegar', 'string', 'sms');
        $draft = $this->draft();
        $this->submit($draft);
        app(CorporateVerificationService::class)->review($draft, User::factory()->create(), ['version' => 1, 'decision' => 'rejected', 'reason' => 'مدارک ناقص']);
        Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200], 'entries' => [['messageid' => 1234]]])]);
        $delivery = CorporateDelivery::whereHas('event', fn ($query) => $query->where('event', 'rejected'))->where('channel', 'sms')->firstOrFail();
        app()->call([new DeliverCorporateNotification($delivery->id), 'handle']);
        $this->assertSame('sent', $delivery->fresh()->status);
        Http::assertSent(fn ($request) => $request['template'] === 'AviatoCorpRejected' && $request['token'] === (string) $draft->id && ! isset($request['sender']));
        app()->call([new DeliverCorporateNotification($delivery->id), 'handle']);
        Http::assertSentCount(1);
    }

    public function test_lookup_timeout_and_mismatched_identity_do_not_create_requests(): void
    {
        Http::swap(new Factory);
        Http::fake(['service.zohal.io/*' => Http::response(['result' => 1, 'response_body' => ['data' => ['national_id' => '99999999999', 'name' => 'wrong']]])]);
        $this->actingAs($this->customer(), 'customer')->post('https://cp.localhost/profile/corporate', ['national_id' => '10123456789'])->assertSessionHasErrors('national_id');
        Http::swap(new Factory);
        Http::fake(['service.zohal.io/*' => Http::failedConnection()]);
        $this->post('https://cp.localhost/profile/corporate', ['national_id' => '10123456789'])->assertSessionHasErrors('national_id');
        $this->assertDatabaseCount('corporate_requests', 0);
    }

    public function test_template_setup_uses_requested_primary_line_and_no_sms_send(): void
    {
        AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, 'test-key', 'string', 'kavenegar');
        Http::fake(['api.kavenegar.com/*/verify/templatelist.json' => Http::response(['return' => ['status' => 200], 'entries' => [], 'metadata' => ['TotalPages' => 1]]), 'api.kavenegar.com/*/verify/addtemplate.json' => Http::response(['return' => ['status' => 200], 'entries' => ['templateid' => '1']])]);
        $this->artisan('corporate:configure-sms --apply')->assertSuccessful();
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/verify/addtemplate.json') && $request['primaryLineNumber'] === '200000171' && $request['fallBackMethod'] === 3);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/verify/lookup'));
    }

    public function test_new_requests_notify_only_selected_authorized_reviewers(): void
    {
        $reviewer = User::factory()->create(['role' => 'custom', 'permissions' => ['corporate.read', 'corporate.review']]);
        $unselected = User::factory()->create();
        $unauthorized = User::factory()->create(['role' => 'custom', 'permissions' => ['tickets.read']]);
        AppSetting::setValue('corporate.reviewer_ids', [$reviewer->id, $unauthorized->id], 'json', 'corporate');
        $draft = $this->draft();
        $this->submit($draft);
        $this->assertSame(3, CorporateDelivery::where('recipient_type', 'admin')->where('recipient_id', $reviewer->id)->count());
        $this->assertSame(0, CorporateDelivery::where('recipient_type', 'admin')->whereIn('recipient_id', [$unselected->id, $unauthorized->id])->count());
        $delivery = CorporateDelivery::where('recipient_type', 'admin')->where('channel', 'in_app')->firstOrFail();
        app()->call([new DeliverCorporateNotification($delivery->id), 'handle']);
        $this->assertSame(1, $reviewer->notifications()->count());
        // The inbox must not implicitly queue a second email outside the outbox.
        $this->assertDatabaseCount('notification_deliveries', 0);
        $this->actingAs($reviewer, 'admin')->get('https://admin.localhost/notifications')->assertOk()->assertJsonPath('unread_count', 1);
    }

    public function test_sms_timeout_is_uncertain_and_requires_reconciliation_before_retry(): void
    {
        AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, 'test-key', 'string', 'kavenegar');
        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar', 'string', 'sms');
        $draft = $this->draft();
        $this->submit($draft);
        $admin = User::factory()->create();
        app(CorporateVerificationService::class)->review($draft, $admin, ['version' => 1, 'decision' => 'rejected', 'reason' => 'مدارک ناقص']);
        Http::fake(['api.kavenegar.com/*' => Http::failedConnection()]);
        $delivery = CorporateDelivery::whereHas('event', fn ($q) => $q->where('event', 'rejected'))->where('channel', 'sms')->firstOrFail();
        app()->call([new DeliverCorporateNotification($delivery->id), 'handle']);
        $this->assertSame('uncertain', $delivery->fresh()->status);
        $this->actingAs($admin, 'admin')->post('https://admin.localhost/corporate-deliveries/'.$delivery->id.'/retry')->assertSessionHasErrors('confirmed_not_sent');
        $this->post('https://admin.localhost/corporate-deliveries/'.$delivery->id.'/retry', ['confirmed_not_sent' => 1])->assertSessionHasNoErrors();
        $this->assertSame('queued', $delivery->fresh()->status);
    }

    public function test_customer_sms_preference_is_respected(): void
    {
        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar', 'string', 'sms');
        $draft = $this->draft();
        $this->submit($draft);
        $draft->customer->forceFill(['sms_notifications_enabled' => false])->save();
        app(CorporateVerificationService::class)->review($draft, User::factory()->create(), ['version' => 1, 'decision' => 'rejected', 'reason' => 'مدارک ناقص']);
        $delivery = CorporateDelivery::whereHas('event', fn ($q) => $q->where('event', 'rejected'))->where('channel', 'sms')->firstOrFail();
        Http::fake();
        app()->call([new DeliverCorporateNotification($delivery->id), 'handle']);
        $this->assertSame('suppressed', $delivery->fresh()->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'kavenegar'));
    }

    public function test_failed_submission_cleans_uploaded_files_and_emits_no_events(): void
    {
        $draft = $this->draft();
        try {
            app(CorporateVerificationService::class)->submit($draft, ['version' => 0, 'representative_role' => 'delegate'], ['incorporation' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
            $this->fail('Missing authority documents accepted');
        } catch (ValidationException) {
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('corporate_request_events', 0);
        $this->assertDatabaseCount('corporate_deliveries', 0);
    }

    public function test_payment_freezes_recipient_on_success_and_legacy_backfill_is_repeatable(): void
    {
        $customer = $this->customer();
        $payment = Payment::create(['customer_id' => $customer->id, 'wallet_id' => $customer->wallet->id, 'provider' => 'dummy', 'type' => 'top_up', 'status' => 'pending', 'amount' => 1000, 'currency' => 'IRR']);
        $this->assertNull($payment->billing_identity);
        $payment->update(['status' => 'successful', 'paid_at' => now()]);
        $identity = $payment->fresh()->billingRecipient();
        $customer->update(['name' => 'Different name']);
        $this->assertSame($identity, $payment->fresh()->billingRecipient());
        $this->artisan('billing:backfill-identities')->assertSuccessful();
        $this->artisan('billing:backfill-identities')->assertSuccessful();
        $this->assertSame($identity, $payment->fresh()->billingRecipient());
        $this->assertDatabaseCount('billing_identities', 1);
        $this->assertTrue(BillingIdentity::firstOrFail()->effective_at->copy()->utc()->greaterThan(Carbon::createFromTimestampUTC(0)));
    }

    public function test_duplicate_open_request_and_claimed_company_are_rejected(): void
    {
        $customer = $this->customer();
        $draft = $this->draft($customer);
        try {
            $this->draft($customer);
            $this->fail('Duplicate draft accepted');
        } catch (ValidationException) {
        }
        $this->submit($draft);
        app(CorporateVerificationService::class)->review($draft, User::factory()->create(), ['version' => 1, 'decision' => 'approved']);
        try {
            $this->draft($this->customer());
            $this->fail('Company claimed by another customer');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('corporate_profiles', 1);
    }

    public function test_settings_require_reviewer_permissions_and_can_be_rendered(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin')->get('https://admin.localhost/settings/corporate')->assertOk()->assertSee('200000171');
        $this->get('https://admin.localhost/corporate-requests')->assertOk();
        $templates = collect(DeliverCorporateNotification::smsTemplates())->map(fn ($item) => $item['name'])->all();
        $data = ['enabled' => 1, 'email_enabled' => 1, 'sms_enabled' => 1, 'in_app_enabled' => 1, 'submission_sms_enabled' => 0, 'templates' => $templates];
        $this->patch('https://admin.localhost/settings/corporate', $data)->assertSessionHasErrors('enabled');
        $this->patch('https://admin.localhost/settings/corporate', $data + ['reviewer_ids' => [$admin->id]])->assertSessionHasNoErrors();
        $this->assertTrue((bool) AppSetting::getValue('corporate.enabled'));
    }

    public function test_configuring_existing_template_preserves_text_and_sets_line(): void
    {
        AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, 'test-key', 'string', 'kavenegar');
        AppSetting::setValue(AppSetting::KAVENEGAR_TEMPLATE, 'LoginOtp', 'string', 'kavenegar');
        Http::fake(['api.kavenegar.com/*/verify/templatelist.json' => Http::response(['return' => ['status' => 200], 'entries' => [['id' => 42, 'name' => 'LoginOtp']]]),
            'api.kavenegar.com/*/verify/gettemplate.json' => Http::response(['return' => ['status' => 200], 'entries' => ['name' => 'LoginOtp', 'smsmessage' => 'کد ورود %token', 'approvalstatus' => 'Approved']]),
            'api.kavenegar.com/*/verify/addtemplate.json' => Http::response(['return' => ['status' => 200]]),
            'api.kavenegar.com/*/verify/updatetemplate.json' => Http::response(['return' => ['status' => 200]])]);
        $this->artisan('corporate:configure-sms --apply --include-existing')->assertSuccessful();
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/verify/updatetemplate.json') && $request['name'] === 'LoginOtp' && $request['textMessage'] === 'کد ورود %token' && $request['primaryLineNumber'] === '200000171');
    }

    public function test_sender_setup_uses_known_ids_and_defers_unapproved_templates(): void
    {
        AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, 'test-key', 'string', 'kavenegar');
        $names = collect(DeliverCorporateNotification::smsTemplates())->pluck('name')->values();
        $mappings = $names->map(fn ($name, $index) => $name.'='.($index + 1))->all();
        Http::fake(['api.kavenegar.com/*/verify/gettemplate.json' => function ($request) use ($names) {
            return Http::response(['return' => ['status' => 200], 'entries' => ['id' => $request['id'], 'name' => $names[(int) $request['id'] - 1], 'smsmessage' => 'آویاتو %token', 'primarylinenumber' => '10004347', 'approvalstatus' => 'PendingReview']]);
        }]);
        $this->artisan('corporate:configure-sms', ['--apply' => true, '--template-id' => $mappings])->assertFailed();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'templatelist') || str_contains($request->url(), 'updatetemplate') || str_contains($request->url(), 'addtemplate'));
    }
}
