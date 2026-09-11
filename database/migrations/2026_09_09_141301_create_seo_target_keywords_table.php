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
        Schema::create('seo_target_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('term');
            $table->string('normalized_term');
            $table->string('priority', 20)->default('normal');
            $table->text('note')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('last_selected_at')->nullable();
            $table->timestamps();
            $table->unique(['website_id', 'normalized_term']);
            $table->index(['website_id', 'archived_at', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_target_keywords');
    }
};
