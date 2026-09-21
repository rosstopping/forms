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
        Schema::table('competitor_audits', function (Blueprint $table): void {
            $table->string('trigger')->default('manual');
            $table->json('comparison_pages')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('competitor_audits', function (Blueprint $table): void {
            $table->dropColumn(['trigger', 'comparison_pages']);
        });
    }
};
