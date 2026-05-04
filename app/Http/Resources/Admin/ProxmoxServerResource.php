<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProxmoxServerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cluster_name' => $this->cluster_name,
            'host' => $this->host,
            'port' => $this->port,
            'realm' => $this->realm,
            'username' => $this->username,
            'api_token_id' => $this->api_token_id,
            'verify_tls' => $this->verify_tls,
            'is_active' => $this->is_active,
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'last_status' => $this->last_status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'summary' => $this->when(isset($this->summary), $this->summary ?? null),
        ];
    }
}
