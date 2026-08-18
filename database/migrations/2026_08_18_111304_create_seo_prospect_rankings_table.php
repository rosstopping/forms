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
        Schema::create('seo_prospect_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_prospect_candidate_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->unsignedSmallInteger('position');
            $table->text('ranking_url');
            $table->string('page_title')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['seo_prospect_candidate_id', 'keyword'], 'seo_prospect_ranking_keyword_unique');
            $table->index(['keyword', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_prospect_rankings');
    }
};
