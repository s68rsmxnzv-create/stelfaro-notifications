<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationAction extends Model
{
    protected $fillable = [
        'notification_activity_id',
        'notification_sender_alias_id',
        'key',
        'name',
        'purpose',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(NotificationActivity::class, 'notification_activity_id');
    }

    public function senderAlias(): BelongsTo
    {
        return $this->belongsTo(NotificationSenderAlias::class, 'notification_sender_alias_id');
    }
}
