<?php

namespace App\Http\Resources\Api;

use App\Models\AppSetting;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $wallets = app(WalletService::class);
        $currency = AppSetting::currency();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => (int) $this->amount,
            'display_amount' => $wallets->format((int) $this->amount, $currency),
            'balance_before' => (int) $this->balance_before,
            'balance_after' => (int) $this->balance_after,
            'description' => $this->description,
            'currency' => $currency,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public function with(Request $request): array
    {
        return ['meta' => ['request_id' => $request->attributes->get('api_request_id')]];
    }
}
