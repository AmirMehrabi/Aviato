<?php

namespace App\Services\Payments;

use App\Models\AppSetting;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
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
            'headers' => ['Idempotency-Key' => 'hesabro-charge-'.$payment->authority],
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
            'provider_order_id' => is_scalar($data['order_id'] ?? null) ? (string) $data['order_id'] : null,
            'provider_model_id' => is_scalar($data['model_id'] ?? null) ? (string) $data['model_id'] : null,
            'username' => $username,
            'amount' => $responseAmount,
            'redirect_url' => $redirectUrl,
            'callback_url' => $responseCallbackUrl,
        ];
    }

    public function complete(Payment $payment, array $payload = []): array
    {
        $orderId = $payload['order_id'] ?? null;
        $modelId = $payload['model_id'] ?? null;
        $success = $payload['success'] ?? null;

        if (! is_scalar($orderId) || ! ctype_digit((string) $orderId)
            || ! is_scalar($modelId) || ! ctype_digit((string) $modelId)
            || ! in_array($success, ['0', '1', 0, 1], true)) {
            throw new HesabroPaymentException('اطلاعات بازگشتی حسابرو معتبر نیست.');
        }
        foreach (['order_id', 'model_id'] as $field) {
            $expected = data_get($payment->gateway_payload, 'provider_'.$field);
            if ($expected !== null && (string) $expected !== (string) $payload[$field]) {
                throw new HesabroPaymentException('شناسه پرداخت حسابرو با درخواست ثبت‌شده همخوانی ندارد.');
            }
        }
        if ((string) $success !== '1') {
            throw new HesabroPaymentException(
                'پرداخت حسابرو ناموفق بود یا توسط کاربر لغو شد.',
                shouldFailPayment: true,
                context: ['stage' => 'callback_failed'],
            );
        }

        return [
            'provider_reference' => (string) $orderId,
            'status' => 'successful',
            'payload' => [
                'callback' => $payload,
                'confirmation_method' => 'signed_browser_callback',
                'confirmed_at' => now()->toIso8601String(),
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

        $path = URL::temporarySignedRoute(
            'customer.wallet.payments.callback',
            now()->addDay(),
            ['payment' => $payment->id],
            absolute: false,
        );

        return "{$scheme}://{$domain}{$path}";
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

        if (! $response->successful() || ! $this->isEnvelopeSuccessful($body)) {
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
}
