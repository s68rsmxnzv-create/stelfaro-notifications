<?php

namespace App\Services;

use App\Models\NotificationSenderAlias;

class SenderAliasResolver
{
    public function resolve(string $purpose): ?NotificationSenderAlias
    {
        return NotificationSenderAlias::query()
            ->where('scope_type', 'global')
            ->where('scope_id', 0)
            ->where('purpose', $purpose)
            ->where('is_active', true)
            ->first();
    }
}
