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
        Schema::create('seo_prospect_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_prospect_search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain');
            $table->string('website_url');
            $table->string('business_name')->nullable();
            $table->string('location');
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->unsignedTinyInteger('audit_score')->nullable();
            $table->string('migration_difficulty', 20)->default('unknown');
            $table->text('migration_difficulty_reason')->nullable();
            $table->unsignedTinyInteger('opportunity_score')->nullable();
            $table->json('score_breakdown')->nullable();
            $table->json('observations')->nullable();
            $table->string('qualification_status', 30)->default('pending_analysis');
            $table->timestamps();

            $table->unique(['seo_prospect_search_id', 'domain'], 'seo_prospect_candidate_domain_unique');
            $table->index(['seo_prospect_search_id', 'qualification_status'], 'seo_prospect_candidate_status_index');
            $table->index('opportunity_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_prospect_candidates');
    }
};
