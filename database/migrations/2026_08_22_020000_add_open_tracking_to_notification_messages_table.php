<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_messages', function (Blueprint $table): void {
            $table->timestamp('opened_at')->nullable()->after('sent_at');
            $table->unsignedInteger('open_count')->default(0)->after('opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('notification_messages', function (Blueprint $table): void {
            $table->dropColumn(['opened_at', 'open_count']);
        });
    }
};
