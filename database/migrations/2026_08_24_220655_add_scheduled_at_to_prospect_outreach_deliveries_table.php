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
        Schema::table('prospect_outreach_deliveries', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('body')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospect_outreach_deliveries', function (Blueprint $table) {
            $table->dropIndex(['scheduled_at']);
            $table->dropColumn('scheduled_at');
        });
    }
};
