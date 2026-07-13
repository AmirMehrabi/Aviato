<?php

namespace App\Http\Resources\Api;

use App\Models\AppSetting;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $wallets = app(WalletService::class);
        $currency = AppSetting::currency();

        return [
            'project_id' => $request->route('project')?->uuid,
            'project_name' => $request->route('project')?->name,
            'balance' => (int) $this->balance,
            'currency' => $currency,
            'display_amount' => $wallets->format((int) $this->balance, $currency),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function with(Request $request): array
    {
        return ['meta' => ['request_id' => $request->attributes->get('api_request_id')]];
    }
}
