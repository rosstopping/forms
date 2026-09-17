<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_impacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('content_generation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key');
            $table->string('title');
            $table->text('hypothesis');
            $table->json('target_urls');
            $table->json('target_queries');
            $table->text('control_url')->nullable();
            $table->string('country', 3)->nullable();
            $table->string('device')->nullable();
            $table->string('primary_metric')->default('clicks');
            $table->unsignedTinyInteger('business_value')->default(3);
            $table->unsignedTinyInteger('confidence')->default(3);
            $table->unsignedTinyInteger('effort')->default(3);
            $table->json('evidence')->nullable();
            $table->string('status')->default('planned');
            $table->timestamp('live_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deployment_evidence')->nullable();
            $table->text('actual_changes')->nullable();
            $table->text('property_url')->nullable();
            $table->json('baseline')->nullable();
            $table->json('observations')->nullable();
            $table->timestamp('last_measured_at')->nullable();
            $table->timestamp('next_measurement_at')->nullable()->index();
            $table->unsignedSmallInteger('review_after_days')->default(28);
            $table->string('outcome')->nullable();
            $table->string('decision')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('measurement_error')->nullable();
            $table->timestamps();
            $table->unique(['website_id', 'source_key']);
            $table->index(['website_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_impacts');
    }
};
