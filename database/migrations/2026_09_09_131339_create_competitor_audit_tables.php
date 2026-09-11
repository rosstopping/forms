<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->boolean('excluded')->default(false);
            $table->string('source')->default('manual');
            $table->timestamps();
            $table->unique(['website_id', 'domain']);
        });
        Schema::create('competitor_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_competitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('competitor_domain');
            $table->string('provider')->default('dataforseo');
            $table->unsignedInteger('location_code');
            $table->string('language_code', 10);
            $table->string('status')->default('pending');
            $table->json('stages')->nullable();
            $table->json('errors')->nullable();
            $table->json('limits');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['website_competitor_id', 'status', 'completed_at'], 'competitor_audit_freshness');
        });
        Schema::create('competitor_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_audit_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('keyword');
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('our_position')->nullable();
            $table->text('ranking_url')->nullable();
            $table->text('our_ranking_url')->nullable();
            $table->string('comparison')->default('unknown');
            $table->unsignedInteger('search_volume')->nullable();
            $table->string('search_intent')->nullable();
            $table->unsignedSmallInteger('keyword_difficulty')->nullable();
            $table->decimal('estimated_traffic', 16, 4)->nullable();
            $table->timestamps();
            $table->unique(['competitor_audit_id', 'fingerprint'], 'competitor_keyword_unique');
        });
        Schema::create('competitor_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_audit_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('url_hash', 64);
            $table->decimal('estimated_traffic', 16, 4)->nullable();
            $table->unsignedInteger('organic_keywords')->nullable();
            $table->string('status')->default('pending');
            $table->json('analysis')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['competitor_audit_id', 'url_hash'], 'competitor_page_unique');
        });
        Schema::create('competitor_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fingerprint', 64);
            $table->string('title');
            $table->string('status')->default('open');
            $table->unsignedSmallInteger('priority_score')->default(0);
            $table->json('brief');
            $table->timestamps();
            $table->unique(['competitor_audit_id', 'fingerprint'], 'competitor_opportunity_unique');
            $table->index(['website_id', 'fingerprint'], 'competitor_brief_deduplication');
        });
        Schema::table('content_requests', function (Blueprint $table) {
            $table->json('competitor_context')->nullable();
            $table->string('competitor_fingerprint', 64)->nullable();
            $table->unique(['website_id', 'competitor_fingerprint'], 'content_competitor_unique');
        });
        Schema::table('content_generations', function (Blueprint $table) {
            $table->json('competitor_context')->nullable();
        });
        Schema::table('external_api_usages', function (Blueprint $table) {
            $table->foreignId('competitor_audit_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('external_api_usages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('competitor_audit_id');
        });
        Schema::table('content_generations', fn (Blueprint $table) => $table->dropColumn('competitor_context'));
        Schema::table('content_requests', function (Blueprint $table) {
            $table->dropUnique('content_competitor_unique');
            $table->dropColumn(['competitor_context', 'competitor_fingerprint']);
        });
        foreach (['competitor_opportunities', 'competitor_pages', 'competitor_keywords', 'competitor_audits', 'website_competitors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
