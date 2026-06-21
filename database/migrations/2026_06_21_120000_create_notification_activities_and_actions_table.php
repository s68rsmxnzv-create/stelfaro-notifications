<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_activities', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_sender_alias_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 80);
            $table->string('name');
            $table->string('purpose', 80)->unique();
            $table->string('status', 32)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['notification_activity_id', 'key']);
            $table->index(['purpose', 'status']);
        });

        $now = now();
        $activityId = DB::table('notification_activities')->insertGetId([
            'key' => 'invitations',
            'name' => 'Invitaciones',
            'description' => 'Correos relacionados con invitaciones de usuarios a la plataforma.',
            'status' => 'active',
            'metadata' => json_encode(['source' => 'system']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('notification_actions')->insert([
            'notification_activity_id' => $activityId,
            'key' => 'platform_invitation',
            'name' => 'Invitacion a plataforma',
            'purpose' => 'platform_invitation',
            'status' => 'active',
            'metadata' => json_encode(['source' => 'system']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_actions');
        Schema::dropIfExists('notification_activities');
    }
};
