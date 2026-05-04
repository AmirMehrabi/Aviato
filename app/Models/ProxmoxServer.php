<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'cluster_name',
    'host',
    'port',
    'realm',
    'username',
    'password',
    'api_token_id',
    'api_token_secret',
    'verify_tls',
    'is_active',
    'last_seen_at',
    'last_status',
])]
#[Hidden(['password', 'api_token_secret'])]
class ProxmoxServer extends Model
{
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'api_token_secret' => 'encrypted',
            'verify_tls' => 'boolean',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_status' => 'array',
        ];
    }

    public function baseUrl(): string
    {
        return 'https://'.rtrim($this->host, '/').':'.$this->port;
    }

    public function usesApiToken(): bool
    {
        return filled($this->api_token_id) && filled($this->api_token_secret);
    }

    public function proxmoxUser(): string
    {
        return str_contains($this->username, '@') ? $this->username : $this->username.'@'.$this->realm;
    }
}
