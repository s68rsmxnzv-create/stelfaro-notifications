<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationMessage extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'empresa_id',
        'recipient_email',
        'recipient_name',
        'subject',
        'notification_sender_alias_id',
        'purpose',
        'from_email',
        'from_name',
        'reply_to_email',
        'reply_to_name',
        'status',
        'provider',
        'provider_message_id',
        'attempts',
        'last_error',
        'metadata',
        'sensitive_metadata',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'empresa_id' => 'integer',
            'attempts' => 'integer',
            'metadata' => 'array',
            'sensitive_metadata' => 'encrypted:array',
            'sent_at' => 'datetime',
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NotificationAttachment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(NotificationEvent::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordEvent(string $type, array $payload = []): NotificationEvent
    {
        return $this->events()->create([
            'type' => $type,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
