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
        Schema::table('websites', function (Blueprint $table) {
            $table->boolean('seo_weekly_snapshots_enabled')->default(false)->after('health_reports_enabled');
            $table->timestamp('seo_history_backfilled_at')->nullable()->after('seo_weekly_snapshots_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn(['seo_weekly_snapshots_enabled', 'seo_history_backfilled_at']);
        });
    }
};
