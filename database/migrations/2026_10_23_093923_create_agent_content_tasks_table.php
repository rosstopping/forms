<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_content_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('scheduled_for');
            $table->string('timezone');
            $table->string('fingerprint', 64);
            $table->string('status')->default('in_progress');
            $table->json('project');
            $table->string('repository');
            $table->string('branch')->nullable();
            $table->json('evidence');
            $table->text('rationale');
            $table->text('pull_request_url')->nullable();
            $table->json('result')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->timestamps();
            $table->unique(['website_id', 'scheduled_for']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_content_tasks');
    }
};
