<?php

namespace App\Services\Payments;

use App\Models\AppSetting;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class HesabroPaymentGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'hesabro';
    }

    public function label(): string
    {
        return 'حسابرو';
    }

    public function isAvailable(): bool
    {
        return AppSetting::hesabroPaymentEnabled() && AppSetting::hesabroPaymentConfigured();
    }

    public function initiate(Payment $payment): array
    {
        $this->ensureConfigured();

        $username = $this->username($payment);
        $callbackUrl = $this->callbackUrl($payment);
        $response = $this->request('POST', $this->endpointPath('/payment-service/wallet/user-charge'), [
            'query' => ['username' => $username],
            'json' => [
                'amount' => $payment->amount,
                'callback_url' => $callbackUrl,
            ],
        ]);
        $data = $this->successfulData($response, 'درخواست شارژ کیف پول حسابرو ایجاد نشد.');
        $redirectUrl = (string) ($data['go_to_ipg_url'] ?? '');
        $responseAmount = (int) ($data['amount'] ?? 0);
        $responseCallbackUrl = (string) ($data['callback_url'] ?? $callbackUrl);

        if ($redirectUrl === '') {
            throw new HesabroPaymentException('لینک پرداخت از حسابرو دریافت نشد.', context: [
                'stage' => 'charge',
                'response' => $data,
            ]);
        }

        if ($responseAmount !== $payment->amount || $responseCallbackUrl !== $callbackUrl) {
            throw new HesabroPaymentException(
                'اطلاعات درخواست شارژ بازگشتی حسابرو با پرداخت ثبت‌شده همخوانی ندارد.',
                shouldFailPayment: true,
                context: [
                    'stage' => 'charge_validation',
                    'payment_amount' => $payment->amount,
                    'response_amount' => $responseAmount,
                    'callback_url' => $callbackUrl,
                    'response_callback_url' => $responseCallbackUrl,
                ],
            );
        }

        return [
            'authority' => $payment->authority,
            'provider' => $this->key(),
            'status' => 'pending',
            'username' => $username,
            'amount' => $responseAmount,
            'redirect_url' => $redirectUrl,
            'callback_url' => $responseCallbackUrl,
        ];
    }

    public function complete(Payment $payment, array $payload = []): array
    {
        if ($this->isFailureCallback($payload)) {
            throw new HesabroPaymentException(
                'پرداخت حسابرو با خطا یا لغو از سمت درگاه بازگشت داده شد.',
                shouldFailPayment: true,
                context: ['stage' => 'callback_failed', 'callback' => $payload],
            );
        }

        $providerReference = $this->providerReference($payload, $payment);

        return [
            'provider_reference' => $providerReference,
            'status' => 'successful',
            'payload' => [
                'callback' => $payload,
                'verified_at' => now()->toIso8601String(),
                'provider_reference' => $providerReference,
                'status' => 'successful',
            ],
        ];
    }

    private function ensureConfigured(): void
    {
        if (! AppSetting::paymentsEnabled() || ! AppSetting::hesabroPaymentEnabled()) {
            throw ValidationException::withMessages([
                'payment' => 'درگاه پرداخت حسابرو در حال حاضر غیرفعال است.',
            ]);
        }

        if (! AppSetting::hesabroPaymentConfigured()) {
            throw ValidationException::withMessages([
                'payment' => 'تنظیمات درگاه پرداخت حسابرو کامل نیست.',
            ]);
        }
    }

    private function username(Payment $payment): string
    {
        $customer = $payment->customer;
        $username = trim((string) $customer->phone);

        if ($username === '') {
            throw ValidationException::withMessages([
                'payment' => 'برای پرداخت با حسابرو، شماره موبایل مشتری باید ثبت شده باشد.',
            ]);
        }

        return $username;
    }

    private function callbackUrl(Payment $payment): string
    {
        $domain = (string) config('portals.customer.domain');
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return "{$scheme}://{$domain}/wallet/payments/{$payment->id}/callback";
    }

    private function endpointPath(string $path): string
    {
        return '/@'.AppSetting::hesabroClient().'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function request(string $method, string $path, array $options = []): Response
    {
        try {
            return Http::baseUrl(rtrim((string) config('payments.hesabro.base_url'), '/'))
                ->withBasicAuth(AppSetting::hesabroClientId(), AppSetting::hesabroClientSecret())
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->connectTimeout(8)
                ->send($method, $path, $options);
        } catch (ConnectionException $exception) {
            throw new HesabroPaymentException('ارتباط با سرویس حسابرو برقرار نشد.', context: [
                'exception' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            throw new HesabroPaymentException('در ارتباط با سرویس حسابرو خطایی رخ داد.', context: [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function successfulData(Response $response, string $fallbackMessage): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw new HesabroPaymentException(
                $fallbackMessage,
                responseCode: (string) $response->status(),
                context: ['status' => $response->status(), 'response' => $body],
            );
        }

        if (! $this->isEnvelopeSuccessful($body)) {
            throw new HesabroPaymentException(
                (string) data_get($body, 'message', data_get($body, 'errors.0.0', data_get($body, 'errors.0.message', $fallbackMessage))),
                responseCode: (string) $response->status(),
                context: ['status' => $response->status(), 'response' => $body],
            );
        }

        $data = data_get($body, 'data');

        return is_array($data) ? $data : $body;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function isEnvelopeSuccessful(array $body): bool
    {
        if (array_key_exists('status', $body)) {
            return filter_var($body['status'], FILTER_VALIDATE_BOOL);
        }

        if (array_key_exists('success', $body)) {
            return filter_var($body['success'], FILTER_VALIDATE_BOOL);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isFailureCallback(array $payload): bool
    {
        $status = strtolower(trim((string) data_get($payload, 'status', data_get($payload, 'result', ''))));
        $result = strtolower(trim((string) data_get($payload, 'payment_status', '')));
        $success = data_get($payload, 'success');
        $code = (string) data_get($payload, 'code', '');
        $resCode = (string) data_get($payload, 'ResCode', data_get($payload, 'res_code', ''));

        return in_array($status, ['failed', 'fail', 'canceled', 'cancelled', 'error'], true)
            || in_array($result, ['failed', 'fail', 'canceled', 'cancelled', 'error'], true)
            || $success === false
            || $code !== '' && $code !== '0' && $code !== '200'
            || $resCode !== '' && ! in_array($resCode, ['0', '00'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function providerReference(array $payload, Payment $payment): string
    {
        foreach (['order_id', 'payment_id', 'transaction_id', 'reference_id', 'ref_id', 'tracking_code', 'authority'] as $key) {
            $value = trim((string) data_get($payload, $key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return $payment->authority;
    }
}
