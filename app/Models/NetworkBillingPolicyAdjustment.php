<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['vm_network_billing_period_id', 'actor_user_id', 'policy_before', 'policy_after', 'amount_before', 'amount_after', 'amount_delta', 'usage_accrual_id', 'accrual_changes', 'wallet_transaction_id'])]
class NetworkBillingPolicyAdjustment extends Model
{
    protected function casts(): array
    {
        return [
            'policy_before' => 'array', 'policy_after' => 'array', 'accrual_changes' => 'array',
            'amount_before' => 'integer', 'amount_after' => 'integer', 'amount_delta' => 'integer',
        ];
    }
}
