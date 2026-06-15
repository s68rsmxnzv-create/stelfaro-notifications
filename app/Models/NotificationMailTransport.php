<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationMailTransport extends Model
{
    protected $fillable = [
        'name',
        'mailer',
        'host',
        'port',
        'scheme',
        'username',
        'password',
        'default_from_email',
        'default_from_name',
        'is_active',
        'last_verified_at',
        'metadata',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
            'is_active' => 'boolean',
            'last_verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
