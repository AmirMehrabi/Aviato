<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'phone', 'national_code', 'national_code_hash', 'national_code_verified_at', 'password', 'email_verified_at', 'email_verification_code', 'email_verification_expires_at', 'status', 'suspended_at', 'suspension_reason'])]
#[Hidden(['password', 'remember_token'])]
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected static function booted(): void
    {
        static::created(function (Customer $customer): void {
            $customer->wallet()->firstOrCreate([], ['balance' => 0]);
            $customer->ensureDefaultProject();
        });
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('period_start');
    }

    public function virtualMachines(): HasMany
    {
        return $this->hasMany(VirtualMachine::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_customer_id');
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function ensureDefaultProject(): Project
    {
        $project = $this->ownedProjects()->where('is_default', true)->first();

        if (! $project) {
            $project = $this->ownedProjects()->create([
                'name' => 'Default Project',
                'slug' => $this->uniqueProjectSlug('default-project'),
                'is_default' => true,
            ]);
        }

        $project->members()->firstOrCreate(
            ['customer_id' => $this->id],
            ['role' => ProjectMember::ROLE_OWNER],
        );

        return $project;
    }

    public function vmUpgradeOrders(): HasMany
    {
        return $this->hasMany(VmUpgradeOrder::class)->latest();
    }

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

    public function hasVerifiedNationalCode(): bool
    {
        return $this->national_code_verified_at !== null;
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
            'email_verification_expires_at' => 'datetime',
            'national_code_verified_at' => 'datetime',
            'national_code' => 'encrypted',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    private function uniqueProjectSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'project';
        $candidate = $slug;
        $suffix = 2;

        while ($this->ownedProjects()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }
}
