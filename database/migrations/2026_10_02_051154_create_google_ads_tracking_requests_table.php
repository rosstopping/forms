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
        Schema::create('google_ads_tracking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_repository_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('customer_id', 10);
            $table->string('conversion_action_id', 20);
            $table->string('conversion_action_name');
            $table->string('send_to', 100);
            $table->string('lead_success_description', 500);
            $table->string('status', 24)->default('queued');
            $table->string('copilot_task_id')->nullable();
            $table->string('copilot_task_url')->nullable();
            $table->string('copilot_task_state')->nullable();
            $table->unsignedBigInteger('pull_request_number')->nullable();
            $table->string('pull_request_url')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['website_id', 'customer_id', 'conversion_action_id'], 'ads_tracking_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_ads_tracking_requests');
    }
};
