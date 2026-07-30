<?php

namespace App\Services\Payments;

use App\Models\AppSetting;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class ZibalPaymentGateway implements PaymentGatewayInterface
{
    private const PAID_CALLBACK_STATUSES = [1, 2];

    private const SUCCESSFUL_VERIFY_RESULTS = [100, 201];

    public function key(): string
    {
        return 'zibal';
    }

    public function label(): string
    {
        return 'زیبال';
    }

    public function isAvailable(): bool
    {
        return AppSetting::zibalPaymentEnabled() && AppSetting::zibalPaymentConfigured();
    }

    public function initiate(Payment $payment): array
    {
        $this->ensureConfigured();

        $callbackUrl = $this->callbackUrl($payment);
        $parameters = [
            'merchant' => AppSetting::zibalMerchant(),
            'amount' => $payment->amount,
            'callbackUrl' => $callbackUrl,
            'orderId' => (string) $payment->id,
            'description' => (string) ($payment->description ?: 'شارژ کیف پول'),
        ];

        $response = $this->request('POST', '/v1/request', $parameters);
        $body = $this->responseBody($response, 'درخواست پرداخت زیبال ایجاد نشد.');
        $result = (int) ($body['result'] ?? -1);
        $trackId = trim((string) ($body['trackId'] ?? ''));

        if ($result !== 100 || $trackId === '') {
            throw new ZibalPaymentException(
                $this->resultMessage($result, 'درخواست پرداخت زیبال ایجاد نشد.'),
                responseCode: (string) $result,
                context: ['stage' => 'request', 'parameters' => $parameters, 'response' => $body],
            );
        }

        return [
            'authority' => $trackId,
            'provider' => $this->key(),
            'status' => 'pending',
            'order_id' => (string) $payment->id,
            'amount' => $payment->amount,
            'callback_url' => $callbackUrl,
            'redirect_url' => rtrim((string) config('payments.zibal.base_url'), '/').'/start/'.$trackId,
            'request' => [
                'result' => $result,
                'track_id' => $trackId,
                'response' => $body,
            ],
        ];
    }

    public function complete(Payment $payment, array $payload = []): array
    {
        $trackId = trim((string) ($payload['trackId'] ?? $payload['track_id'] ?? ''));
        $orderId = trim((string) ($payload['orderId'] ?? $payload['order_id'] ?? ''));
        $success = $this->isTruthy($payload['success'] ?? false);
        $status = (int) ($payload['status'] ?? 0);

        if ($trackId === '' || $trackId !== (string) $payment->authority || ($orderId !== '' && $orderId !== (string) $payment->id)) {
            throw new ZibalPaymentException(
                'اطلاعات بازگشتی درگاه زیبال با پرداخت ثبت‌شده همخوانی ندارد.',
                shouldFailPayment: true,
                responseCode: (string) ($payload['result'] ?? $status),
                context: ['stage' => 'callback_validation', 'callback' => $payload],
            );
        }

        if (! $success || ! in_array($status, self::PAID_CALLBACK_STATUSES, true)) {
            throw new ZibalPaymentException(
                $this->statusMessage($status),
                shouldFailPayment: true,
                responseCode: (string) $status,
                context: ['stage' => 'callback', 'callback' => $payload],
            );
        }

        $response = $this->request('POST', '/v1/verify', [
            'merchant' => AppSetting::zibalMerchant(),
            'trackId' => $trackId,
        ]);
        $body = $this->responseBody($response, 'تایید پرداخت زیبال انجام نشد.');
        $result = (int) ($body['result'] ?? -1);

        if (! in_array($result, self::SUCCESSFUL_VERIFY_RESULTS, true)) {
            throw new ZibalPaymentException(
                $this->resultMessage($result, 'پرداخت زیبال تایید نشد.'),
                shouldFailPayment: true,
                responseCode: (string) $result,
                context: ['stage' => 'verify', 'callback' => $payload, 'response' => $body],
            );
        }

        $verifiedAmount = (int) ($body['amount'] ?? 0);
        $verifiedOrderId = trim((string) ($body['orderId'] ?? ''));

        if (($verifiedAmount > 0 && $verifiedAmount !== (int) $payment->amount)
            || ($verifiedOrderId !== '' && $verifiedOrderId !== (string) $payment->id)) {
            throw new ZibalPaymentException(
                'مبلغ یا شناسه سفارش تاییدشده زیبال با پرداخت ثبت‌شده همخوانی ندارد.',
                shouldFailPayment: true,
                responseCode: (string) $result,
                context: [
                    'stage' => 'verify_validation',
                    'payment_amount' => $payment->amount,
                    'verified_amount' => $verifiedAmount,
                    'payment_order_id' => (string) $payment->id,
                    'verified_order_id' => $verifiedOrderId,
                    'response' => $body,
                ],
            );
        }

        $providerReference = trim((string) ($body['refNumber'] ?? $body['ref_number'] ?? ''));

        if ($providerReference === '') {
            throw new ZibalPaymentException(
                'شماره مرجع تراکنش از زیبال دریافت نشد.',
                shouldFailPayment: true,
                responseCode: (string) $result,
                context: ['stage' => 'verify_reference', 'response' => $body],
            );
        }

        return [
            'provider_reference' => $providerReference,
            'status' => 'successful',
            'payload' => [
                'callback' => $payload,
                'verify' => $body,
                'verify_result' => $result,
                'verified_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function ensureConfigured(): void
    {
        if (! AppSetting::paymentsEnabled() || ! AppSetting::zibalPaymentEnabled()) {
            throw ValidationException::withMessages([
                'payment' => 'درگاه پرداخت زیبال در حال حاضر غیرفعال است.',
            ]);
        }

        if (! AppSetting::zibalPaymentConfigured()) {
            throw ValidationException::withMessages([
                'payment' => 'تنظیمات درگاه پرداخت زیبال کامل نیست.',
            ]);
        }
    }

    private function callbackUrl(Payment $payment): string
    {
        $domain = (string) config('portals.customer.domain');
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return "{$scheme}://{$domain}/wallet/payments/{$payment->id}/callback";
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function request(string $method, string $path, array $parameters): Response
    {
        try {
            return Http::baseUrl(rtrim((string) config('payments.zibal.base_url'), '/'))
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->connectTimeout(8)
                ->send($method, $path, ['json' => $parameters]);
        } catch (ConnectionException $exception) {
            throw new ZibalPaymentException('ارتباط با سرویس زیبال برقرار نشد.', context: [
                'exception' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            throw new ZibalPaymentException('در ارتباط با سرویس زیبال خطایی رخ داد.', context: [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function responseBody(Response $response, string $fallbackMessage): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw new ZibalPaymentException($fallbackMessage, responseCode: (string) $response->status(), context: [
                'status' => $response->status(),
                'response' => $body,
            ]);
        }

        return $body;
    }

    private function isTruthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ((int) $value === 1);
    }

    private function resultMessage(int $result, string $fallback): string
    {
        return match ($result) {
            100 => 'عملیات با موفقیت انجام شد.',
            102 => 'مرچنت زیبال پیدا نشد.',
            103 => 'مرچنت زیبال فعال نیست.',
            104 => 'مرچنت زیبال معتبر نیست.',
            201 => 'این تراکنش قبلا تایید شده است.',
            202 => 'سفارش پرداخت نشده یا ناموفق است.',
            203 => 'شناسه پیگیری زیبال معتبر نیست.',
            default => $fallback,
        };
    }

    private function statusMessage(int $status): string
    {
        return match ($status) {
            3 => 'پرداخت توسط مشتری لغو شد.',
            5 => 'موجودی کارت کافی نیست.',
            6 => 'رمز کارت نامعتبر است.',
            15 => 'تراکنش برگشت داده شده است.',
            18 => 'تراکنش معکوس شده است.',
            default => 'پرداخت زیبال انجام نشد.',
        };
    }
}
