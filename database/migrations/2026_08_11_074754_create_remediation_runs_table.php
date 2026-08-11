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
        Schema::create('remediation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_health_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_repository_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('awaiting_runner');
            $table->json('findings');
            $table->string('base_sha', 64)->nullable();
            $table->string('branch')->nullable();
            $table->string('commit_sha', 64)->nullable();
            $table->unsignedBigInteger('pull_request_number')->nullable();
            $table->text('pull_request_url')->nullable();
            $table->string('pull_request_state')->nullable();
            $table->text('summary')->nullable();
            $table->json('verification')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();

            $table->index(['website_repository_id', 'status']);
            $table->unique(['website_health_report_id', 'website_repository_id'], 'remediation_report_repository_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('remediation_runs');
    }
};
