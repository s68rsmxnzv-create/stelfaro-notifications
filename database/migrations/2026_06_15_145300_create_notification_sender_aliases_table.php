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
        Schema::create('notification_sender_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 32)->default('global');
            $table->unsignedBigInteger('scope_id')->default(0);
            $table->string('purpose', 80);
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('reply_to_email')->nullable();
            $table->string('reply_to_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id', 'purpose']);
            $table->index(['purpose', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_sender_aliases');
    }
};
