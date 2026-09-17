<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_impact_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seo_impact_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('checkpoint');
            $table->date('period_start');
            $table->date('period_end');
            $table->json('baseline');
            $table->json('measurement');
            $table->json('assessment');
            $table->string('outcome');
            $table->timestamps();
            $table->unique(['seo_impact_id', 'checkpoint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_impact_reviews');
    }
};
