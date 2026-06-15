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
        Schema::create('notification_mail_transports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mailer', 32)->default('smtp');
            $table->string('host');
            $table->unsignedInteger('port')->default(465);
            $table->string('scheme', 16)->nullable();
            $table->string('username');
            $table->text('password')->nullable();
            $table->string('default_from_email');
            $table->string('default_from_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_mail_transports');
    }
};
