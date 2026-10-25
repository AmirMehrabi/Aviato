<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'customer_id', 'managed_domain_id', 'domain_product_id', 'contact_mode', 'domain', 'operation', 'period', 'status', 'currency', 'supplier_price', 'subtotal', 'tax_amount', 'tax_rate', 'total', 'quoted_until', 'registrant', 'nameservers', 'consented_at', 'payment_id', 'wallet_transaction_id', 'refund_transaction_id', 'invoice_id', 'funded_at', 'completed_at', 'expiry_before', 'customer_message'])]
class DomainOrder extends Model
{
    protected function casts(): array
    {
        return ['period' => 'integer', 'supplier_price' => 'integer', 'subtotal' => 'integer', 'tax_amount' => 'integer', 'tax_rate' => 'float', 'total' => 'integer', 'quoted_until' => 'datetime', 'registrant' => 'encrypted:array', 'nameservers' => 'array', 'consented_at' => 'datetime', 'funded_at' => 'datetime', 'completed_at' => 'datetime', 'expiry_before' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DomainProduct::class, 'domain_product_id');
    }

    public function managedDomain(): BelongsTo
    {
        return $this->belongsTo(ManagedDomain::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(DomainOperation::class);
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['funded', 'processing', 'confirming', 'needs_review'], true);
    }

    public function label(): string
    {
        return match ($this->status) {
            'draft' => 'آماده تکمیل سفارش', 'awaiting_payment' => 'در انتظار پرداخت', 'funded' => 'مبلغ رزرو شد', 'processing' => 'در حال انجام', 'confirming' => 'در حال تأیید نتیجه', 'needs_review' => 'در حال پیگیری با سرویس‌دهنده', 'completed' => 'انجام شد', 'failed' => 'انجام نشد؛ مبلغ آزاد شد', 'cancelled' => 'لغو شد', default => 'نیاز به بررسی'
        };
    }
}
