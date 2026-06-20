<?php

namespace App\Services\Payments;

use App\Models\AppSetting;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

class MellatPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly MellatClientInterface $client) {}

    public function key(): string
    {
        return 'mellat';
    }

    public function label(): string
    {
        return 'بانک ملت';
    }

    public function isAvailable(): bool
    {
        return AppSetting::mellatPaymentEnabled() && AppSetting::mellatPaymentConfigured();
    }

    public function initiate(Payment $payment): array
    {
        $this->ensureConfigured();

        $now = now();
        $parameters = $this->baseParameters() + [
            'orderId' => $payment->id,
            'amount' => $payment->amount,
            'localDate' => $now->format('Ymd'),
            'localTime' => $now->format('His'),
            'additionalData' => mb_substr((string) $payment->description, 0, 1000),
            'callBackUrl' => $this->callbackUrl($payment),
            'payerId' => '0',
        ];

        [$code, $refId] = $this->splitPayResponse($this->client->bpPayRequest($parameters));

        if ($code !== '0' || $refId === '') {
            throw new MellatPaymentException(
                $this->responseMessage($code),
                responseCode: $code,
                context: ['stage' => 'pay_request', 'parameters' => $this->safeParameters($parameters)],
            );
        }

        return [
            'authority' => $refId,
            'status' => 'pending',
            'provider' => 'mellat',
            'order_id' => $payment->id,
            'callback_url' => $parameters['callBackUrl'],
            'redirect_url' => $this->redirectUrl(),
            'pay_request' => [
                'res_code' => $code,
                'ref_id' => $refId,
            ],
        ];
    }

    public function complete(Payment $payment, array $payload = []): array
    {
        $this->ensureConfigured();

        $refId = (string) ($payload['RefId'] ?? $payload['ref_id'] ?? '');
        $resCode = (string) ($payload['ResCode'] ?? $payload['res_code'] ?? '');
        $saleOrderId = (string) ($payload['SaleOrderId'] ?? $payload['sale_order_id'] ?? '');
        $saleReferenceId = (string) ($payload['SaleReferenceId'] ?? $payload['sale_reference_id'] ?? '');

        if ($refId === '' || $refId !== (string) $payment->authority || $saleOrderId !== (string) $payment->id) {
            throw new MellatPaymentException(
                'اطلاعات بازگشتی درگاه با پرداخت ثبت شده همخوانی ندارد.',
                shouldFailPayment: true,
                responseCode: $resCode ?: null,
                context: ['stage' => 'callback_validation', 'callback' => $payload],
            );
        }

        if ($resCode !== '0') {
            throw new MellatPaymentException(
                $this->responseMessage($resCode),
                shouldFailPayment: true,
                responseCode: $resCode,
                context: ['stage' => 'callback', 'callback' => $payload],
            );
        }

        if ($saleReferenceId === '' || ! ctype_digit($saleReferenceId)) {
            throw new MellatPaymentException(
                'شماره مرجع تراکنش از درگاه پرداخت دریافت نشد.',
                shouldFailPayment: true,
                responseCode: $resCode,
                context: ['stage' => 'callback_reference', 'callback' => $payload],
            );
        }

        $verifySettleCode = $this->client->bpVerifySettleRequest($this->verificationParameters($payment, $saleReferenceId));

        if ($verifySettleCode === '43') {
            $settleCode = $this->client->bpSettleRequest($this->verificationParameters($payment, $saleReferenceId));

            if (! in_array($settleCode, ['0', '45'], true)) {
                throw new MellatPaymentException(
                    $this->responseMessage($settleCode),
                    responseCode: $settleCode,
                    context: ['stage' => 'settle', 'callback' => $payload],
                );
            }
        } elseif (! in_array($verifySettleCode, ['0', '45'], true)) {
            throw new MellatPaymentException(
                $this->responseMessage($verifySettleCode),
                responseCode: $verifySettleCode,
                context: ['stage' => 'verify_settle', 'callback' => $payload],
            );
        }

        return [
            'provider_reference' => $saleReferenceId,
            'status' => 'successful',
            'payload' => [
                'callback' => $payload,
                'verify_settle_res_code' => $verifySettleCode,
            ],
        ];
    }

    public function redirectUrl(): string
    {
        $key = AppSetting::mellatPaymentMode() === 'production' ? 'production_redirect_url' : 'test_redirect_url';

        return (string) config("payments.mellat.{$key}");
    }

    private function ensureConfigured(): void
    {
        if (! AppSetting::paymentsEnabled() || ! AppSetting::mellatPaymentEnabled()) {
            throw ValidationException::withMessages([
                'payment' => 'درگاه پرداخت ملت در حال حاضر غیرفعال است.',
            ]);
        }

        if (! AppSetting::mellatPaymentConfigured()) {
            throw ValidationException::withMessages([
                'payment' => 'تنظیمات درگاه پرداخت ملت کامل نیست.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function baseParameters(): array
    {
        return [
            'terminalId' => (int) AppSetting::mellatTerminalId(),
            'userName' => AppSetting::mellatUsername(),
            'userPassword' => AppSetting::mellatPassword(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function verificationParameters(Payment $payment, string $saleReferenceId): array
    {
        return $this->baseParameters() + [
            'orderId' => $payment->id,
            'saleOrderId' => $payment->id,
            'saleReferenceId' => (int) $saleReferenceId,
        ];
    }

    private function callbackUrl(Payment $payment): string
    {
        $domain = (string) config('portals.customer.domain');
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return "{$scheme}://{$domain}/wallet/payments/{$payment->id}/callback";
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPayResponse(string $response): array
    {
        $parts = array_map('trim', explode(',', $response, 2));

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function safeParameters(array $parameters): array
    {
        unset($parameters['userPassword']);

        return $parameters;
    }

    private function responseMessage(?string $code): string
    {
        return match ((string) $code) {
            '0' => 'تراکنش با موفقیت انجام شد.',
            '11' => 'شماره کارت نامعتبر است.',
            '12' => 'موجودی کارت کافی نیست.',
            '13' => 'رمز کارت نادرست است.',
            '17' => 'پرداخت توسط کاربر لغو شد.',
            '21' => 'پذیرنده نامعتبر است.',
            '24' => 'اطلاعات کاربری پذیرنده نامعتبر است.',
            '25' => 'مبلغ پرداخت نامعتبر است.',
            '34' => 'خطای سیستمی در درگاه پرداخت رخ داد.',
            '41' => 'شماره درخواست پرداخت تکراری است.',
            '43' => 'تراکنش قبلا تایید شده است.',
            '45' => 'تراکنش قبلا تسویه شده است.',
            '48' => 'تراکنش برگشت خورده است.',
            '61' => 'خطا در واریز تراکنش رخ داد.',
            '421' => 'IP سرور پذیرنده برای درگاه ملت ثبت نشده است.',
            default => 'پرداخت از سوی درگاه ملت تایید نشد.',
        };
    }
}
