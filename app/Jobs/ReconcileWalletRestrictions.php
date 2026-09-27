<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\WalletRestrictionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ReconcileWalletRestrictions implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 120;

    public function __construct(public readonly int $customerId) {}

    public function uniqueId(): string
    {
        return (string) $this->customerId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('wallet-restrictions:'.$this->customerId))->releaseAfter(30)->expireAfter(300)];
    }

    public function handle(WalletRestrictionService $restrictions): void
    {
        $customer = Customer::find($this->customerId);

        if ($customer) {
            $restrictions->reconcile($customer);
        }
    }
}
