<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_setups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->json('saved_steps')->nullable();
            $table->string('current_step')->default('business');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_setups');
    }
};
