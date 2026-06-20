<?php

namespace App\Services\Payments;

use App\Models\AppSetting;
use SoapClient;
use Throwable;

class MellatSoapClient implements MellatClientInterface
{
    public function bpPayRequest(array $parameters): string
    {
        return $this->call('bpPayRequest', $parameters);
    }

    public function bpVerifySettleRequest(array $parameters): string
    {
        return $this->call('bpVerifySettleRequest', $parameters);
    }

    public function bpSettleRequest(array $parameters): string
    {
        return $this->call('bpSettleRequest', $parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function call(string $method, array $parameters): string
    {
        try {
            $response = $this->client()->__soapCall($method, [$parameters]);
        } catch (Throwable $exception) {
            throw new MellatPaymentException(
                'ارتباط با درگاه پرداخت ملت برقرار نشد. لطفا چند دقیقه دیگر دوباره تلاش کنید.',
                context: [
                    'method' => $method,
                    'exception' => $exception->getMessage(),
                ],
            );
        }

        return $this->normalizeResponse($response);
    }

    private function client(): SoapClient
    {
        return new SoapClient($this->wsdlUrl(), [
            'encoding' => 'UTF-8',
            'exceptions' => true,
            'trace' => false,
        ]);
    }

    private function wsdlUrl(): string
    {
        $key = AppSetting::mellatPaymentMode() === 'production' ? 'production_wsdl' : 'test_wsdl';

        return (string) config("payments.mellat.{$key}");
    }

    private function normalizeResponse(mixed $response): string
    {
        if (is_scalar($response) || $response === null) {
            return trim((string) $response);
        }

        if (is_object($response) && isset($response->return)) {
            return trim((string) $response->return);
        }

        if (is_array($response) && array_key_exists('return', $response)) {
            return trim((string) $response['return']);
        }

        throw new MellatPaymentException('پاسخ درگاه پرداخت ملت قابل پردازش نیست.');
    }
}
