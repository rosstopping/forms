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
        Schema::table('content_generations', function (Blueprint $table) {
            $table->unsignedBigInteger('opportunity_root_id')->nullable()->index();
            $table->unsignedTinyInteger('opportunity_attempt')->default(0);
            $table->json('completion_evidence')->nullable();
            $table->unique(['content_plan_id', 'scheduled_for', 'opportunity_attempt'], 'content_opportunity_attempt_unique');
            $table->dropUnique(['content_plan_id', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->unique(['content_plan_id', 'scheduled_for']);
            $table->dropUnique('content_opportunity_attempt_unique');
            $table->dropColumn(['opportunity_root_id', 'opportunity_attempt', 'completion_evidence']);
        });
    }
};
