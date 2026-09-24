<?php

namespace App\Models;

use App\Services\VmActivityRecorder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'customer_id',
    'virtual_machine_id',
    'from_bundle_id',
    'to_bundle_id',
    'type',
    'status',
    'before_snapshot',
    'after_snapshot',
    'minimum_wallet_balance',
    'estimated_monthly_delta',
    'proxmox_task_id',
    'progress',
    'last_attempt_at',
    'reconcile_after',
    'failure_reason',
    'applied_at',
])]
class VmUpgradeOrder extends Model
{
    protected static function booted(): void
    {
        static::updated(function (VmUpgradeOrder $order): void {
            if (! $order->wasChanged('status') || ! in_array($order->status, [self::STATUS_SUCCEEDED, self::STATUS_FAILED, self::STATUS_RECONCILIATION_REQUIRED], true)) {
                return;
            }

            $vm = $order->virtualMachine;

            if (! $vm) {
                return;
            }

            $title = $order->type === self::TYPE_BUNDLE ? 'ارتقای پلن' : 'ارتقای دیسک';
            $outcome = match ($order->status) {
                self::STATUS_SUCCEEDED => 'succeeded',
                self::STATUS_FAILED => 'failed',
                default => 'pending',
            };
            $detail = match ($outcome) {
                'succeeded' => 'عملیات با موفقیت انجام شد.',
                'failed' => 'عملیات کامل نشد. لطفاً با پشتیبانی تماس بگیرید.',
                default => 'نتیجه زیرساخت در حال بررسی است.',
            };

            app(VmActivityRecorder::class)->record($vm, 'upgrade', $outcome, $title.' · '.($outcome === 'succeeded' ? 'انجام شد' : ($outcome === 'failed' ? 'ناموفق' : 'نیازمند بررسی')), $detail, metadata: ['order_id' => $order->id]);
        });
    }

    public const TYPE_BUNDLE = 'bundle';

    public const TYPE_PRIMARY_DISK = 'primary_disk';

    public const TYPE_EXTRA_DISK = 'extra_disk';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLYING = 'applying';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RECONCILIATION_REQUIRED = 'reconciliation_required';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    public function fromBundle(): BelongsTo
    {
        return $this->belongsTo(VmBundle::class, 'from_bundle_id');
    }

    public function toBundle(): BelongsTo
    {
        return $this->belongsTo(VmBundle::class, 'to_bundle_id');
    }

    public function disk(): HasOne
    {
        return $this->hasOne(VmDisk::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPLYING, self::STATUS_RECONCILIATION_REQUIRED], true);
    }

    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'progress' => 'array',
            'minimum_wallet_balance' => 'integer',
            'estimated_monthly_delta' => 'integer',
            'applied_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'reconcile_after' => 'datetime',
        ];
    }
}
