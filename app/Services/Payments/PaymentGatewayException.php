<?php

namespace App\Services\Payments;

use RuntimeException;

class PaymentGatewayException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly bool $shouldFailPayment = false,
        public readonly ?string $responseCode = null,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
