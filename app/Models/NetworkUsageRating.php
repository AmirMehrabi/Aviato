<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['network_usage_bucket_id', 'vm_network_billing_period_id', 'revision', 'ingress_bytes', 'egress_bytes', 'rated_bytes', 'amount_delta', 'period_amount_after', 'policy_snapshot', 'usage_accrual_id', 'wallet_transaction_id'])]
class NetworkUsageRating extends Model
{
    public function bucket(): BelongsTo
    {
        return $this->belongsTo(NetworkUsageBucket::class, 'network_usage_bucket_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(VmNetworkBillingPeriod::class, 'vm_network_billing_period_id');
    }

    protected function casts(): array
    {
        return ['revision' => 'integer', 'ingress_bytes' => 'integer', 'egress_bytes' => 'integer', 'rated_bytes' => 'integer', 'amount_delta' => 'integer', 'period_amount_after' => 'integer', 'policy_snapshot' => 'array'];
    }
}
