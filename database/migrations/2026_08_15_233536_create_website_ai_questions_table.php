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
        Schema::create('website_ai_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('status', 20)->default('processing');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'website_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_ai_questions');
    }
};
