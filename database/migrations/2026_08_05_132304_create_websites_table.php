<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_discovered')->default(false);
            $table->boolean('email_enabled')->default(true);
            $table->json('email_recipients')->nullable();
            $table->boolean('webhook_enabled')->default(false);
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->string('success_redirect_url')->nullable();
            $table->string('failure_redirect_url')->nullable();
            $table->boolean('turnstile_enabled')->default(false);
            $table->string('turnstile_secret_key')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
