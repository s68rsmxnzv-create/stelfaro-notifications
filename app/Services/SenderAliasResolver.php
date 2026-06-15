<?php

namespace App\Services;

use App\Models\NotificationSenderAlias;

class SenderAliasResolver
{
    public function resolve(string $purpose, ?int $empresaId = null): ?NotificationSenderAlias
    {
        if ($empresaId !== null) {
            $alias = NotificationSenderAlias::query()
                ->where('scope_type', 'empresa')
                ->where('scope_id', $empresaId)
                ->where('purpose', $purpose)
                ->where('is_active', true)
                ->first();

            if ($alias) {
                return $alias;
            }
        }

        return NotificationSenderAlias::query()
            ->where('scope_type', 'global')
            ->where('scope_id', 0)
            ->where('purpose', $purpose)
            ->where('is_active', true)
            ->first();
    }
}
