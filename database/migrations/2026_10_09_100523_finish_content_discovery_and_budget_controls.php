<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table): void {
            $table->boolean('keyword_research_enabled')->default(false);
            $table->timestamp('keyword_researched_at')->nullable();
            $table->json('keyword_research')->nullable();
            $table->string('article_path', 200)->nullable();
        });
        Schema::table('content_generations', function (Blueprint $table): void {
            $table->timestamp('budget_reserved_at')->nullable();
        });
        Schema::table('content_requests', function (Blueprint $table): void {
            $table->timestamp('budget_reserved_at')->nullable();
            $table->string('budget_work_type')->nullable();
            $table->json('discovery_context')->nullable();
            $table->index(['website_id', 'budget_reserved_at']);
        });
        Schema::create('content_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fingerprint', 64);
            $table->string('title');
            $table->string('status')->default('open');
            $table->unsignedTinyInteger('priority_score')->default(40);
            $table->json('brief');
            $table->timestamp('collected_at');
            $table->timestamps();
            $table->unique(['website_id', 'fingerprint']);
            $table->index(['website_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_opportunities');
        Schema::table('content_generations', function (Blueprint $table): void {
            $table->dropColumn('budget_reserved_at');
        });
        Schema::table('content_requests', function (Blueprint $table): void {
            $table->dropIndex(['website_id', 'budget_reserved_at']);
            $table->dropColumn(['budget_reserved_at', 'budget_work_type', 'discovery_context']);
        });
        Schema::table('content_plans', function (Blueprint $table): void {
            $table->dropColumn(['keyword_research_enabled', 'keyword_researched_at', 'keyword_research', 'article_path']);
        });
    }
};
