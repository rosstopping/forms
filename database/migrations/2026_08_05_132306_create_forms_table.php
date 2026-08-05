<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_discovered')->default(false);
            $table->boolean('email_enabled_override')->nullable();
            $table->json('email_recipients_override')->nullable();
            $table->string('email_subject_override')->nullable();
            $table->boolean('webhook_enabled_override')->nullable();
            $table->string('webhook_url_override')->nullable();
            $table->string('webhook_secret_override')->nullable();
            $table->string('success_redirect_url_override')->nullable();
            $table->string('failure_redirect_url_override')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_submission_at')->nullable();
            $table->timestamps();
            $table->unique(['website_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
