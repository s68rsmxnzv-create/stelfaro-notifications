<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $activityId = DB::table('notification_activities')->where('key', 'users')->value('id');

        if (! $activityId) {
            $activityId = DB::table('notification_activities')->insertGetId([
                'key' => 'users',
                'name' => 'Usuarios',
                'description' => 'Correos relacionados con usuarios y accesos temporales.',
                'status' => 'active',
                'metadata' => json_encode(['source' => 'system']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('notification_actions')->where('purpose', 'platform_temporary_password')->exists()) {
            DB::table('notification_actions')->insert([
                'notification_activity_id' => $activityId,
                'key' => 'temporary_password_delivery',
                'name' => 'Envio de clave temporal',
                'purpose' => 'platform_temporary_password',
                'status' => 'active',
                'metadata' => json_encode(['source' => 'system']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('notification_actions')->where('purpose', 'platform_temporary_password')->delete();
    }
};
