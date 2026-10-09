<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table): void {
            $table->boolean('trend_research_enabled')->default(false);
            $table->timestamp('trend_researched_at')->nullable();
            $table->json('trend_research')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table): void {
            $table->dropColumn(['trend_research_enabled', 'trend_researched_at', 'trend_research']);
        });
    }
};
