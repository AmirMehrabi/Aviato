<?php

namespace App\Exceptions;

use RuntimeException;

class S3Exception extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
        public readonly ?string $resource = null,
    ) {
        parent::__construct($message);
    }
}
