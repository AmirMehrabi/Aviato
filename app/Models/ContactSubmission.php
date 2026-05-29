<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'email',
    'phone',
    'need_type',
    'team_size',
    'message',
    'status',
    'ip_address',
    'user_agent',
])]
class ContactSubmission extends Model
{
    public const STATUS_NEW = 'new';
}
