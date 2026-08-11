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
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('website_url');
            $table->string('status', 30)->default('new');
            $table->string('analysis_status', 30)->default('pending');
            $table->unsignedTinyInteger('opportunity_score')->nullable();
            $table->json('findings')->nullable();
            $table->text('analysis_error')->nullable();
            $table->timestamp('analysed_at')->nullable();
            $table->string('outreach_subject')->nullable();
            $table->text('outreach_body')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('suppressed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'next_follow_up_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
