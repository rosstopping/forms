<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_visibility_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->json('providers')->nullable();
            $table->unsignedSmallInteger('frequency_days')->default(7);
            $table->string('brand_name')->nullable();
            $table->json('aliases')->nullable();
            $table->json('services')->nullable();
            $table->json('locations')->nullable();
            $table->timestamps();
        });
        Schema::create('ai_visibility_prompts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seo_target_keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->text('prompt');
            $table->char('fingerprint', 64);
            $table->string('topic')->nullable();
            $table->string('location')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['website_id', 'active']);
            $table->unique(['website_id', 'fingerprint']);
        });
        Schema::create('ai_visibility_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_visibility_prompt_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('model');
            $table->text('prompt_snapshot');
            $table->char('prompt_fingerprint', 64);
            $table->json('identity_snapshot');
            $table->char('cohort', 64);
            $table->date('period_start');
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->mediumText('response_text')->nullable();
            $table->boolean('brand_mentioned')->nullable();
            $table->unsignedSmallInteger('brand_position')->nullable();
            $table->boolean('website_mentioned')->nullable();
            $table->boolean('website_cited')->nullable();
            $table->json('citations')->nullable();
            $table->json('competitors')->nullable();
            $table->json('analysis')->nullable();
            $table->json('usage')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
            $table->unique(['ai_visibility_prompt_id', 'provider', 'period_start'], 'ai_visibility_result_once_per_week');
            $table->index(['website_id', 'status', 'checked_at'], 'ai_visibility_reporting');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visibility_results');
        Schema::dropIfExists('ai_visibility_prompts');
        Schema::dropIfExists('ai_visibility_settings');
    }
};
