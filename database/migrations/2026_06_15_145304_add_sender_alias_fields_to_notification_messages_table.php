<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_messages', function (Blueprint $table) {
            $table->foreignId('notification_sender_alias_id')->nullable()->after('subject')->constrained()->nullOnDelete();
            $table->string('purpose', 80)->default('dte_delivery')->after('subject');
            $table->string('from_email')->nullable()->after('purpose');
            $table->string('from_name')->nullable()->after('from_email');
            $table->string('reply_to_email')->nullable()->after('from_name');
            $table->string('reply_to_name')->nullable()->after('reply_to_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('notification_sender_alias_id');
            $table->dropColumn([
                'purpose',
                'from_email',
                'from_name',
                'reply_to_email',
                'reply_to_name',
            ]);
        });
    }
};
