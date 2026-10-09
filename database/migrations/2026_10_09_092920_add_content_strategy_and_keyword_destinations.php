<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table): void {
            $table->string('content_mode')->default('balanced');
            $table->boolean('discovery_enabled')->default(false);
            $table->unsignedInteger('monthly_article_limit')->nullable();
            $table->unsignedInteger('monthly_optimisation_limit')->nullable();
            $table->unsignedInteger('monthly_copilot_limit')->nullable();
        });
        Schema::table('content_generations', function (Blueprint $table): void {
            $table->json('work_units')->nullable();
        });
        Schema::table('content_requests', function (Blueprint $table): void {
            $table->string('work_type')->default('unspecified');
            $table->string('planning_status')->default('queued');
            $table->date('planned_for')->nullable();
        });
        Schema::table('seo_target_keywords', function (Blueprint $table): void {
            $table->string('intended_url', 700)->nullable();
            $table->string('assignment_role')->default('supporting');
            $table->string('search_intent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_generations', function (Blueprint $table): void {
            $table->dropColumn('work_units');
        });
        Schema::table('seo_target_keywords', function (Blueprint $table): void {
            $table->dropColumn(['intended_url', 'assignment_role', 'search_intent']);
        });
        Schema::table('content_requests', function (Blueprint $table): void {
            $table->dropColumn(['work_type', 'planning_status', 'planned_for']);
        });
        Schema::table('content_plans', function (Blueprint $table): void {
            $table->dropColumn(['content_mode', 'discovery_enabled', 'monthly_article_limit', 'monthly_optimisation_limit', 'monthly_copilot_limit']);
        });
    }
};
