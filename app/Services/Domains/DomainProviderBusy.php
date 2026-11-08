<?php

namespace App\Services\Domains;

use RuntimeException;

class DomainProviderBusy extends RuntimeException
{
    public function __construct(public readonly int $retryAfter = 5, public readonly bool $requestSent = false)
    {
        parent::__construct('بررسی دامنه موقتاً شلوغ است. کمی صبر کنید و دوباره تلاش کنید.');
    }
}
