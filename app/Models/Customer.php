<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'email_verified_at', 'status', 'suspended_at', 'suspension_reason'])]
#[Hidden(['password', 'remember_token'])]
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    public function suspend(?string $reason = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ])->save();
    }

    public function activate(): void
    {
        $this->forceFill([
            'status' => self::STATUS_ACTIVE,
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
