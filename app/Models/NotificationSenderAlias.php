<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSenderAlias extends Model
{
    protected $fillable = [
        'scope_type',
        'scope_id',
        'purpose',
        'from_email',
        'from_name',
        'reply_to_email',
        'reply_to_name',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scope_id' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
