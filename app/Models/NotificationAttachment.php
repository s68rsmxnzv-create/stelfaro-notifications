<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationAttachment extends Model
{
    protected $fillable = [
        'notification_message_id',
        'type',
        'filename',
        'mime',
        'content_hash',
        'disk',
        'storage_path',
        'source_url',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(NotificationMessage::class, 'notification_message_id');
    }
}
