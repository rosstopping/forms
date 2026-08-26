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
        Schema::table('prospects', function (Blueprint $table) {
            $table->foreignId('prospecting_industry_profile_id')->nullable()->after('website_id')->constrained()->nullOnDelete();
            $table->foreignId('prospecting_location_id')->nullable()->after('prospecting_industry_profile_id')->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('commercial_opportunity_score')->nullable()->after('opportunity_score')->index();
            $table->json('prospecting_context')->nullable()->after('commercial_opportunity_score');

            $table->index(['prospecting_industry_profile_id', 'status'], 'prospects_industry_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex('prospects_industry_status_index');
            $table->dropIndex(['commercial_opportunity_score']);
            $table->dropConstrainedForeignId('prospecting_location_id');
            $table->dropConstrainedForeignId('prospecting_industry_profile_id');
            $table->dropColumn(['commercial_opportunity_score', 'prospecting_context']);
        });
    }
};
