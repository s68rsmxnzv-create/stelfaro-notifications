<?php

namespace Database\Seeders;

use App\Models\NotificationSenderAlias;
use Illuminate\Database\Seeder;

class PlatformSenderAliasSeeder extends Seeder
{
    /**
     * Alias de remitente para correos de plataforma que no dependen de un tenant.
     *
     * Idempotente: se puede correr en cada despliegue. Solo crea los alias que
     * falten; los existentes (y sus ajustes hechos desde el panel) no se tocan.
     */
    public function run(): void
    {
        $aliases = [
            [
                'purpose' => 'platform_account_activation',
                'from_email' => 'activaciondecuenta@stelfaro.com',
                'from_name' => 'Activación de cuenta StelFaro',
            ],
        ];

        foreach ($aliases as $alias) {
            NotificationSenderAlias::query()->firstOrCreate(
                [
                    'purpose' => $alias['purpose'],
                    'scope_type' => 'global',
                    'scope_id' => 0,
                ],
                [
                    'from_email' => $alias['from_email'],
                    'from_name' => $alias['from_name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
