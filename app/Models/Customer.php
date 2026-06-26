<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'first_name', 'last_name', 'email', 'phone', 'national_code', 'national_code_hash', 'national_code_verified_at', 'password', 'email_verified_at', 'email_verification_code', 'email_verification_expires_at', 'status', 'suspended_at', 'suspension_reason', 'sms_notifications_enabled', 'is_reseller', 'reseller_commission_pct', 'reseller_payout_method', 'reseller_earnings_balance', 'reseller_code', 'reseller_status', 'reseller_activated_at'])]
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

    // --- Reseller relationships ---

    public function resellerAssignments(): HasMany
    {
        return $this->hasMany(ResellerCustomer::class, 'reseller_id');
    }

    public function assignedToReseller(): HasMany
    {
        return $this->hasMany(ResellerCustomer::class, 'customer_id');
    }

    public function resellerCommissions(): HasMany
    {
        return $this->hasMany(ResellerCommission::class, 'reseller_id');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(ResellerWithdrawalRequest::class, 'reseller_id');
    }

    public function activeResellerAssignments(): HasMany
    {
        return $this->resellerAssignments()->whereNull('unassigned_at');
    }

    public function isReseller(): bool
    {
        return (bool) $this->is_reseller && $this->reseller_status === 'active';
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

    public function smsNotificationsEnabled(): bool
    {
        return (bool) ($this->sms_notifications_enabled ?? true);
    }

    protected function firstName(): Attribute
    {
        return Attribute::get(function (?string $value, array $attributes): string {
            if (! empty($attributes['first_name'])) {
                return $attributes['first_name'];
            }

            $source = trim((string) ($value ?: ($attributes['name'] ?? '')));

            if ($source === '') {
                return '';
            }

            $spacePosition = strpos($source, ' ');

            return $spacePosition === false ? $source : substr($source, 0, $spacePosition);
        });
    }

    protected function lastName(): Attribute
    {
        return Attribute::get(function (?string $value, array $attributes): string {
            if (! empty($attributes['last_name'])) {
                return $attributes['last_name'];
            }

            $source = trim((string) ($value ?: ($attributes['name'] ?? '')));

            if ($source === '') {
                return '';
            }

            $spacePosition = strpos($source, ' ');

            return $spacePosition === false ? '' : substr($source, $spacePosition + 1);
        });
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
            'sms_notifications_enabled' => 'boolean',
            'is_reseller' => 'boolean',
            'reseller_commission_pct' => 'decimal:2',
            'reseller_earnings_balance' => 'integer',
            'reseller_activated_at' => 'datetime',
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
