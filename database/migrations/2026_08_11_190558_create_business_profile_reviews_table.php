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
        Schema::create('business_profile_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_connection_id')->constrained()->cascadeOnDelete();
            $table->string('google_review_name')->unique();
            $table->string('reviewer_name')->nullable();
            $table->unsignedTinyInteger('star_rating');
            $table->text('comment')->nullable();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->text('google_reply')->nullable();
            $table->text('suggested_reply')->nullable();
            $table->string('reply_status')->default('unanswered')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_profile_reviews');
    }
};
