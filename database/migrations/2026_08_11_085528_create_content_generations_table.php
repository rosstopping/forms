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
        Schema::create('content_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_repository_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('scheduled_for');
            $table->string('status')->default('pending');
            $table->json('search_performance')->nullable();
            $table->text('prompt')->nullable();
            $table->uuid('copilot_task_id')->nullable()->unique();
            $table->text('copilot_task_url')->nullable();
            $table->string('copilot_task_state')->nullable();
            $table->unsignedBigInteger('pull_request_number')->nullable();
            $table->text('pull_request_url')->nullable();
            $table->string('pull_request_state')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();

            $table->index(['content_plan_id', 'status', 'created_at']);
            $table->unique(['content_plan_id', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_generations');
    }
};
