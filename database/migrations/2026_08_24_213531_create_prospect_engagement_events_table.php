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
        Schema::create('prospect_engagement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_outreach_delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prospect_outreach_link_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('source', 40)->default('tracking');
            $table->string('fingerprint', 191)->nullable()->unique();
            $table->smallInteger('score_delta')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['prospect_id', 'event_type', 'occurred_at'], 'pee_prospect_type_occurred_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospect_engagement_events');
    }
};
