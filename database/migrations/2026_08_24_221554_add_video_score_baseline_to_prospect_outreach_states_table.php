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
        Schema::table('prospect_outreach_states', function (Blueprint $table) {
            $table->unsignedInteger('video_sent_engagement_score')->nullable()->after('video_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospect_outreach_states', function (Blueprint $table) {
            $table->dropColumn('video_sent_engagement_score');
        });
    }
};
