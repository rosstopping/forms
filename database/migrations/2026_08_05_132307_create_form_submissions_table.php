<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_url')->nullable();
            $table->string('source_domain')->nullable();
            $table->json('data')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_spam')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('email_failed_at')->nullable();
            $table->text('email_error')->nullable();
            $table->timestamp('webhook_sent_at')->nullable();
            $table->timestamp('webhook_failed_at')->nullable();
            $table->integer('webhook_status_code')->nullable();
            $table->text('webhook_response')->nullable();
            $table->text('webhook_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
