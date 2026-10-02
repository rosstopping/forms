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
        Schema::table('google_ads_campaign_drafts', function (Blueprint $table) {
            $table->unsignedBigInteger('max_cpc_micros')->nullable()->after('daily_budget_micros');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_ads_campaign_drafts', function (Blueprint $table) {
            $table->dropColumn('max_cpc_micros');
        });
    }
};
