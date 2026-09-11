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
        Schema::create('website_audits', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->string('website_url');
            $table->string('domain');
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedTinyInteger('opportunity_score')->nullable();
            $table->json('findings')->nullable();
            $table->json('contact_details')->nullable();
            $table->text('analysis_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['domain', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_audits');
    }
};
