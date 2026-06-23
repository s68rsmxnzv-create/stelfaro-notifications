<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_messages', function (Blueprint $table): void {
            $table->text('sensitive_metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notification_messages', function (Blueprint $table): void {
            $table->dropColumn('sensitive_metadata');
        });
    }
};
