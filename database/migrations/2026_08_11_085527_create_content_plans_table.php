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
        Schema::create('content_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedTinyInteger('weekday')->default(1);
            $table->unsignedTinyInteger('hour')->default(8);
            $table->string('timezone')->default('Europe/London');
            $table->text('audience')->nullable();
            $table->text('guidance')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'weekday', 'hour']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_plans');
    }
};
