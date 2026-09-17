<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jev_shadow_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_visibility_result_id')->constrained()->cascadeOnDelete();
            $table->char('input_hash', 64);
            $table->string('model');
            $table->string('question_version', 32);
            $table->json('request_snapshot');
            $table->json('baseline_snapshot');
            $table->string('status', 20)->default('running');
            $table->string('returned_model')->nullable();
            $table->json('answers')->nullable();
            $table->double('mention_probability')->nullable();
            $table->string('reference_choice', 40)->nullable();
            $table->double('choice_confidence')->nullable();
            $table->json('usage')->nullable();
            $table->decimal('cost', 16, 8)->nullable();
            $table->string('cost_currency', 3)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_type', 40)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('human_label')->nullable();
            $table->string('ambiguity', 100)->nullable();
            $table->json('adjudication')->nullable();
            $table->json('outcome_references')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['ai_visibility_result_id', 'input_hash', 'model', 'question_version'], 'jev_shadow_evaluation_identity');
            $table->index(['website_id', 'status'], 'jev_shadow_website_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jev_shadow_evaluations');
    }
};
