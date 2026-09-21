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
        Schema::table('content_plans', function (Blueprint $table): void {
            $table->string('competitor_research_mode')->default('manual');
            $table->timestamp('competitor_researched_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table): void {
            $table->dropColumn(['competitor_research_mode', 'competitor_researched_at']);
        });
    }
};
