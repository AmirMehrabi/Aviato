<?php

namespace Tests\Feature;

use App\Jobs\SubmitPaymentToHesabro;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\HesabroAccountingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HesabroAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portals.admin.domain' => 'admin.localhost', 'portals.customer.domain' => 'cp.localhost']);
    }

    public function test_admin_can_configure_accounting_without_exposing_password(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')->patch('https://admin.localhost/settings/hesabro-accounting', [
            'hesabro_accounting_enabled' => 1,
            'hesabro_accounting_username' => 'api-user',
            'hesabro_accounting_password' => 'api-secret',
            'hesabro_accounting_client' => 'sabz-co',
            'hesabro_accounting_product_id' => 42,
        ])->assertRedirect('https://admin.localhost/settings/hesabro-accounting');

        $this->assertTrue(AppSetting::hesabroAccountingEnabled());
        $this->assertSame('api-secret', AppSetting::hesabroAccountingPassword());
        $this->assertNotSame('api-secret', AppSetting::getValue(AppSetting::HESABRO_ACCOUNTING_PASSWORD));
        $this->actingAs($admin, 'admin')->get('https://admin.localhost/settings/hesabro-accounting')
            ->assertOk()->assertDontSee('api-secret');
    }

    public function test_successful_payment_can_be_queued_once_from_admin_page(): void
    {
        Queue::fake();
        $this->configureAccounting();
        $payment = $this->payment();
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')->post('https://admin.localhost/billing/payments/'.$payment->id.'/hesabro')
            ->assertRedirect();
        $this->actingAs($admin, 'admin')->post('https://admin.localhost/billing/payments/'.$payment->id.'/hesabro')
            ->assertRedirect();

        $this->assertSame('queued', $payment->fresh()->hesabro_status);
        Queue::assertPushed(SubmitPaymentToHesabro::class, 1);
    }

    public function test_job_sends_service_factor_with_stable_idempotency_key(): void
    {
        $this->configureAccounting();
        $payment = $this->payment();
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['factor_id' => 123]], 200)]);

        (new SubmitPaymentToHesabro($payment->id))->handle(app(HesabroAccountingService::class));
        (new SubmitPaymentToHesabro($payment->id))->handle(app(HesabroAccountingService::class));

        Http::assertSent(function ($request) use ($payment): bool {
            return $request->url() === 'https://hesabro.ir/api/hesabro/@sabz-co/ipg/factor/create-serve'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('api-user:api-secret'))
                && $request->hasHeader('Idempotency-Key', $payment->fresh()->hesabro_idempotency_key)
                && $request['customer']['mobile'] === '09123456789'
                && $request['details'][0]['product_id'] === 42
                && $request['details'][0]['amount'] === $payment->amount;
        });
        $this->assertSame('submitted', $payment->fresh()->hesabro_status);
        $this->assertSame(123, $payment->fresh()->hesabro_factor_id);
        Http::assertSentCount(1);
    }

    public function test_validation_failure_is_recorded_without_throwing(): void
    {
        $this->configureAccounting();
        $payment = $this->payment();
        Http::fake(['*' => Http::response(['success' => false, 'data' => []], 422)]);

        (new SubmitPaymentToHesabro($payment->id))->handle(app(HesabroAccountingService::class));

        $this->assertSame('failed', $payment->fresh()->hesabro_status);
        $this->assertSame(1, $payment->fresh()->hesabro_attempts);
        $this->assertSame(Payment::STATUS_SUCCESSFUL, $payment->fresh()->status);
    }

    public function test_hesabro_failure_does_not_reverse_gateway_payment_or_wallet_credit(): void
    {
        $this->configureAccounting();
        $payment = $this->payment();
        $payment->forceFill(['status' => Payment::STATUS_PENDING, 'paid_at' => null, 'provider' => 'dummy'])->save();
        Http::fake(['*' => Http::response(['success' => false], 500)]);

        $completed = app(PaymentService::class)->completeTopUp($payment);

        $this->assertSame(Payment::STATUS_SUCCESSFUL, $completed->fresh()->status);
        $this->assertSame($payment->amount, $payment->customer->wallet->fresh()->balance);
    }

    private function configureAccounting(): void
    {
        AppSetting::setValue(AppSetting::HESABRO_ACCOUNTING_ENABLED, true);
        AppSetting::setValue(AppSetting::HESABRO_ACCOUNTING_USERNAME, 'api-user');
        AppSetting::setValue(AppSetting::HESABRO_ACCOUNTING_PASSWORD, Crypt::encryptString('api-secret'));
        AppSetting::setValue(AppSetting::HESABRO_ACCOUNTING_CLIENT, 'sabz-co');
        AppSetting::setValue(AppSetting::HESABRO_ACCOUNTING_PRODUCT_ID, 42);
    }

    private function payment(): Payment
    {
        $customer = Customer::factory()->create(['phone' => '09123456789']);

        return Payment::create([
            'customer_id' => $customer->id,
            'wallet_id' => $customer->wallet->id,
            'provider' => 'mellat',
            'type' => Payment::TYPE_TOP_UP,
            'status' => Payment::STATUS_SUCCESSFUL,
            'amount' => 100000,
            'currency' => 'IRR',
            'paid_at' => now(),
        ]);
    }
}
