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
        Schema::create('prospect_outreach_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('lifecycle_state', 40)->default('new')->index();
            $table->unsignedInteger('engagement_score')->default(0);
            $table->string('temperature_override', 20)->nullable();
            $table->string('automation_status', 20)->default('active')->index();
            $table->string('sequence_step', 40)->default('awaiting_initial_email');
            $table->unsignedTinyInteger('follow_up_attempts')->default(0);
            $table->timestamp('initial_email_sent_at')->nullable();
            $table->timestamp('last_outreach_at')->nullable();
            $table->timestamp('last_engagement_at')->nullable();
            $table->timestamp('video_sent_at')->nullable();
            $table->timestamp('post_video_follow_up_sent_at')->nullable();
            $table->timestamp('next_action_at')->nullable()->index();
            $table->timestamp('future_opportunity_at')->nullable();
            $table->timestamp('manual_follow_up_required_at')->nullable()->index();
            $table->json('manual_follow_up_reason')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->string('stop_reason', 40)->nullable();
            $table->timestamps();

            $table->index(['automation_status', 'next_action_at'], 'pos_automation_next_action_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospect_outreach_states');
    }
};
