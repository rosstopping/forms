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
        Schema::create('onboarding_lifecycle_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('step', 40);
            $table->string('audience', 20)->default('customer');
            $table->timestamp('scheduled_for')->index();
            $table->timestamp('queued_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('suppressed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'step']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_lifecycle_messages');
    }
};
