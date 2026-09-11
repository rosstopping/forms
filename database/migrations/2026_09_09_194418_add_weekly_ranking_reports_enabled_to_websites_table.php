<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->boolean('weekly_ranking_reports_enabled')->default(false)->after('health_reports_enabled');
        });

        DB::table('websites')
            ->where('health_reports_enabled', true)
            ->update(['weekly_ranking_reports_enabled' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropColumn('weekly_ranking_reports_enabled');
        });
    }
};
