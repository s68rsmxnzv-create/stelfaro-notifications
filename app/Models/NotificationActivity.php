<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationActivity extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function actions(): HasMany
    {
        return $this->hasMany(NotificationAction::class);
    }
}
