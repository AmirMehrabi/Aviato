<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'customer_id', 'invited_by_customer_id', 'role', 'vm_access_scope'])]
class ProjectMember extends Model
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    public const ROLE_VIEWER = 'viewer';

    public const ROLE_BILLING = 'billing';

    public const VM_ACCESS_ALL = 'all';

    public const VM_ACCESS_OWN = 'own';

    public const VM_ACCESS_SPECIFIC = 'specific';

    public static function roles(): array
    {
        return [
            self::ROLE_OWNER,
            self::ROLE_ADMIN,
            self::ROLE_MEMBER,
            self::ROLE_VIEWER,
            self::ROLE_BILLING,
        ];
    }

    public static function vmAccessScopes(): array
    {
        return [
            self::VM_ACCESS_ALL,
            self::VM_ACCESS_OWN,
            self::VM_ACCESS_SPECIFIC,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProjectMember $member): void {
            $member->vm_access_scope ??= $member->defaultVmAccessScope();
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'invited_by_customer_id');
    }

    public function specificVirtualMachines(): BelongsToMany
    {
        return $this->belongsToMany(VirtualMachine::class, 'project_member_virtual_machines')
            ->withTimestamps();
    }

    public function canManageMembers(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    public function canManageVms(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MEMBER], true);
    }

    public function canViewVms(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MEMBER, self::ROLE_VIEWER], true);
    }

    public function canViewBilling(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_BILLING], true);
    }

    public static function defaultVmAccessScopeForRole(?string $role): string
    {
        return $role === self::ROLE_MEMBER
            ? self::VM_ACCESS_OWN
            : self::VM_ACCESS_ALL;
    }

    public function defaultVmAccessScope(): string
    {
        return self::defaultVmAccessScopeForRole($this->role);
    }

    public function canAccessAllVms(): bool
    {
        return $this->role === self::ROLE_OWNER || $this->vm_access_scope === self::VM_ACCESS_ALL;
    }

    public function canAccessOwnVms(): bool
    {
        return $this->vm_access_scope === self::VM_ACCESS_OWN;
    }

    public function usesSpecificVmScope(): bool
    {
        return $this->vm_access_scope === self::VM_ACCESS_SPECIFIC;
    }
}
