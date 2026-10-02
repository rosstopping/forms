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
        Schema::table('google_ads_connections', function (Blueprint $table) {
            $table->json('campaign_form_draft')->nullable();
            $table->timestamp('campaign_form_draft_saved_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_ads_connections', function (Blueprint $table) {
            $table->dropColumn(['campaign_form_draft', 'campaign_form_draft_saved_at']);
        });
    }
};
