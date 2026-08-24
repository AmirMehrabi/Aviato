<?php

namespace App\Models;

use App\Enums\AdminAbility;
use App\Enums\AdminRole;
use App\Support\AdminAccess;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'is_active', 'last_login_at', 'last_login_ip'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'admin',
        'is_active' => true,
    ];

    public function supportTeams(): BelongsToMany
    {
        return $this->belongsToMany(SupportTeam::class)
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_user_id');
    }

    public function adminTablePreferences(): HasMany
    {
        return $this->hasMany(AdminTablePreference::class);
    }

    public function adminDashboardWarningDismissals(): HasMany
    {
        return $this->hasMany(AdminDashboardWarningDismissal::class);
    }

    public function adminAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'actor_user_id');
    }

    public function scopeSupportAgents(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereIn('role', [AdminRole::Admin, AdminRole::Support]);
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
            'password' => 'hashed',
            'role' => AdminRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function allows(AdminAbility|string $ability): bool
    {
        return AdminAccess::allows($this, $ability);
    }
}
