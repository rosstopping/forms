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
        Schema::create('seo_target_keyword_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_target_keyword_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('dataforseo');
            $table->unsignedInteger('location_code');
            $table->string('language_code', 12);
            $table->string('device', 20)->default('desktop');
            $table->string('status', 30);
            $table->unsignedSmallInteger('position')->nullable();
            $table->text('ranking_url')->nullable();
            $table->text('error')->nullable();
            $table->boolean('cached')->default(false);
            $table->string('provider_task_id')->nullable();
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->index(['seo_target_keyword_id', 'location_code', 'language_code', 'device', 'observed_at'], 'target_rankings_market_observed_index');
            $table->index(['website_id', 'observed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_target_keyword_rankings');
    }
};
