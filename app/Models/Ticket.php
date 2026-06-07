<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'customer_id',
    'virtual_machine_id',
    'ticket_category_id',
    'support_team_id',
    'assigned_user_id',
    'created_by_user_id',
    'created_by_customer_id',
    'subject',
    'status',
    'priority',
    'source',
    'last_customer_reply_at',
    'last_admin_reply_at',
    'last_activity_at',
    'closed_at',
])]
class Ticket extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ANSWERED = 'answered';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public static function statuses(): array
    {
        return [
            self::STATUS_OPEN => 'باز',
            self::STATUS_PENDING => 'در انتظار مشتری',
            self::STATUS_ANSWERED => 'پاسخ داده شده',
            self::STATUS_CLOSED => 'بسته',
        ];
    }

    public static function priorities(): array
    {
        return [
            self::PRIORITY_LOW => 'کم',
            self::PRIORITY_NORMAL => 'عادی',
            self::PRIORITY_HIGH => 'زیاد',
            self::PRIORITY_URGENT => 'فوری',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function supportTeam(): BelongsTo
    {
        return $this->belongsTo(SupportTeam::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function publicMessages(): HasMany
    {
        return $this->messages()->where('type', TicketMessage::TYPE_REPLY);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
    }

    protected function casts(): array
    {
        return [
            'last_customer_reply_at' => 'datetime',
            'last_admin_reply_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
