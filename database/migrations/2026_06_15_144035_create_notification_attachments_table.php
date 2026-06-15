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
        Schema::create('notification_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_message_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('filename');
            $table->string('mime', 120)->nullable();
            $table->string('content_hash', 64);
            $table->string('disk', 64)->nullable();
            $table->string('storage_path')->nullable();
            $table->text('source_url')->nullable();
            $table->timestamps();

            $table->unique(['notification_message_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_attachments');
    }
};
