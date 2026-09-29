<?php

namespace App\Services;

use App\Jobs\SubmitPaymentToHesabro;
use App\Models\AppSetting;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HesabroAccountingService
{
    public function enqueue(Payment $payment): bool
    {
        if (! AppSetting::hesabroAccountingEnabled() || ! AppSetting::hesabroAccountingConfigured()) {
            return false;
        }

        $claimed = DB::transaction(function () use ($payment): bool {
            $locked = Payment::query()->lockForUpdate()->find($payment->id);
            if (! $locked || ! $locked->isSuccessful() || in_array($locked->hesabro_status, ['queued', 'submitted'], true)) {
                return false;
            }
            $locked->forceFill([
                'hesabro_status' => 'queued',
                'hesabro_error' => null,
                'hesabro_idempotency_key' => $locked->hesabro_idempotency_key ?: (string) Str::uuid(),
            ])->save();

            return true;
        });
        if (! $claimed) {
            return false;
        }

        try {
            SubmitPaymentToHesabro::dispatch($payment->id);
        } catch (Throwable $exception) {
            $payment->forceFill(['hesabro_status' => 'failed', 'hesabro_error' => 'Queue dispatch failed.'])->save();
            report($exception);

            return false;
        }

        return true;
    }

    /** @return array{factor_id: int} */
    public function submit(Payment $payment): array
    {
        if (! $payment->isSuccessful() || ! AppSetting::hesabroAccountingEnabled() || ! AppSetting::hesabroAccountingConfigured()) {
            throw new RuntimeException('Hesabro accounting is not ready for this payment.');
        }

        $customer = $payment->customer;
        if (! $customer || ! $customer->phone || ! $customer->first_name) {
            throw new HesabroSubmissionException('Customer mobile and first name are required for Hesabro.', false);
        }
        if ($payment->currency !== 'IRR') {
            throw new HesabroSubmissionException('Only IRR payments can be submitted to Hesabro.', false);
        }
        if (! $payment->hesabro_idempotency_key) {
            $payment->forceFill(['hesabro_idempotency_key' => (string) Str::uuid()])->save();
        }

        $setting = fn (string $key): int => (int) AppSetting::getValue($key, 0);
        $document = [
            'customer' => [
                'mobile' => $customer->phone,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name ?: '',
                'national_code' => $customer->national_code ?: '',
            ],
            'factor' => [
                'branch_id' => $setting(AppSetting::HESABRO_ACCOUNTING_BRANCH_ID),
                'model_id' => $setting(AppSetting::HESABRO_ACCOUNTING_MODEL_ID),
                'm_id_debtor' => $setting(AppSetting::HESABRO_ACCOUNTING_M_ID_DEBTOR),
                't_id_debtor_other' => $setting(AppSetting::HESABRO_ACCOUNTING_T_ID_DEBTOR_OTHER),
                't_id_debtor' => $setting(AppSetting::HESABRO_ACCOUNTING_T_ID_DEBTOR),
                'date' => ($payment->paid_at ?? $payment->created_at)->format('Y-m-d'),
                'description' => $payment->description ?: 'شارژ کیف پول',
                'link' => route('admin.billing.payments.show', $payment),
            ],
            'details' => [[
                'product_id' => $setting(AppSetting::HESABRO_ACCOUNTING_PRODUCT_ID),
                'count' => 1,
                'amount' => $payment->amount,
                'm_id_creditor' => $setting(AppSetting::HESABRO_ACCOUNTING_M_ID_CREDITOR),
                't_id_creditor_other' => $setting(AppSetting::HESABRO_ACCOUNTING_T_ID_CREDITOR_OTHER),
                't_id_creditor' => $setting(AppSetting::HESABRO_ACCOUNTING_T_ID_CREDITOR),
            ]],
        ];

        $client = trim((string) AppSetting::getValue(AppSetting::HESABRO_ACCOUNTING_CLIENT, ''));
        $url = rtrim((string) config('payments.hesabro.accounting_base_url'), '/').'/@'.ltrim($client, '@').'/ipg/factor/create-serve';

        try {
            $response = Http::withBasicAuth(
                (string) AppSetting::getValue(AppSetting::HESABRO_ACCOUNTING_USERNAME, ''),
                AppSetting::hesabroAccountingPassword(),
            )->acceptJson()->asJson()->connectTimeout(5)->timeout(15)
                ->withHeaders(['Idempotency-Key' => $payment->hesabro_idempotency_key])
                ->post($url, $document);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Hesabro connection failed.', previous: $exception);
        }

        if (! $response->successful() || $response->json('success') !== true || ! is_numeric($response->json('data.factor_id'))) {
            $fields = collect($response->json('data'))
                ->pluck('field')->filter(fn ($field): bool => is_string($field) && preg_match('/^[a-z_]+$/i', $field) === 1)
                ->take(5)->implode(', ');
            $message = 'Hesabro rejected the payment (HTTP '.$response->status().($fields !== '' ? '; fields: '.$fields : '').').';

            throw new HesabroSubmissionException($message, $response->status() >= 500 || $response->status() === 429);
        }

        return ['factor_id' => (int) $response->json('data.factor_id')];
    }
}
