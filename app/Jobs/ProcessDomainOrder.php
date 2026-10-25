<?php

namespace App\Jobs;

use App\Models\DomainOrder;
use App\Services\Domains\DomainFulfillmentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessDomainOrder implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 360;

    public int $timeout = 240;

    public int $tries = 1;

    public function __construct(public int $orderId) {}

    public function uniqueId(): string
    {
        return 'domain-order-'.$this->orderId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(300)->dontRelease()];
    }

    public function handle(DomainFulfillmentService $fulfillment): void
    {
        $order = DomainOrder::find($this->orderId);
        if ($order) {
            $fulfillment->process($order);
        }
    }
}
