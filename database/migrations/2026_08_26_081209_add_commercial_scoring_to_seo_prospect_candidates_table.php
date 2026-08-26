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
        Schema::table('seo_prospect_candidates', function (Blueprint $table) {
            $table->unsignedTinyInteger('commercial_opportunity_score')->nullable()->after('opportunity_score')->index();
            $table->json('commercial_score_breakdown')->nullable()->after('score_breakdown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_prospect_candidates', function (Blueprint $table) {
            $table->dropIndex(['commercial_opportunity_score']);
            $table->dropColumn(['commercial_opportunity_score', 'commercial_score_breakdown']);
        });
    }
};
