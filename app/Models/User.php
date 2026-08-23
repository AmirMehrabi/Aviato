<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'can_manage_promotions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'can_manage_promotions' => 'boolean',
        ];
    }

    public function isPromotionSuperAdmin(): bool
    {
        return $this->email !== null
            && in_array(strtolower($this->email), array_map('strtolower', config('promotions.super_admin_emails', [])), true);
    }

    public function canManagePromotions(): bool
    {
        return $this->isPromotionSuperAdmin() || $this->can_manage_promotions;
    }
}
