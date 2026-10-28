<?php

namespace App\Models\Concerns;

use App\Models\DomainOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Corporate\BillingIdentityService;

trait HasBillingIdentity
{
    public static function bootHasBillingIdentity(): void
    {
        static::saving(function ($document): void {
            if ($document->billing_identity || ! $document->customer_id) {
                return;
            }
            if (! $document->canSnapshotBillingIdentity()) {
                return;
            }
            $document->billing_identity = $document->resolveBillingIdentity();
        });
    }

    public function canSnapshotBillingIdentity(): bool
    {
        return match (true) {
            $this instanceof Payment => $this->isSuccessful(),
            $this instanceof DomainOrder => $this->funded_at !== null,
            default => $this->status !== Invoice::STATUS_DRAFT,
        };
    }

    public function resolveBillingIdentity(): array
    {
        if ($this instanceof Invoice) {
            if ($this->kind === 'domain' && $this->domain_order_id) {
                $order = DomainOrder::find($this->domain_order_id);
                if ($order?->billing_identity) {
                    return $order->billing_identity;
                }
            }
            $at = $this->kind === 'usage' ? $this->period_start : ($this->issued_at ?? $this->created_at ?? now());
        } else {
            $at = $this->paid_at ?? $this->funded_at ?? $this->created_at ?? now();
        }

        return app(BillingIdentityService::class)->at($this->customer, $at ?? now());
    }

    public function billingRecipient(): array
    {
        // Legacy fallback until billing:backfill-identities has been run.
        return $this->billing_identity ?? $this->resolveBillingIdentity();
    }
}
