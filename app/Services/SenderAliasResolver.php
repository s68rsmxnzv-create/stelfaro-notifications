<?php

namespace App\Services;

use App\Models\NotificationAction;
use App\Models\NotificationSenderAlias;

class SenderAliasResolver
{
    public function resolve(string $purpose): ?NotificationSenderAlias
    {
        $action = NotificationAction::query()
            ->with('senderAlias')
            ->where('purpose', $purpose)
            ->where('status', 'active')
            ->first();

        if ($action?->senderAlias?->is_active) {
            return $action->senderAlias;
        }

        return NotificationSenderAlias::query()
            ->where('scope_type', 'global')
            ->where('scope_id', 0)
            ->where('purpose', $purpose)
            ->where('is_active', true)
            ->first();
    }
}
