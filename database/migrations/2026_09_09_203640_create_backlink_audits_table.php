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
        Schema::create('backlink_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('provider', 40)->default('dataforseo');
            $table->string('status', 30)->default('pending');
            $table->json('stages')->nullable();
            $table->json('errors')->nullable();
            $table->json('limits');
            $table->json('overview')->nullable();
            $table->json('new_lost_trend')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['website_id', 'status', 'completed_at'], 'backlink_audit_freshness');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backlink_audits');
    }
};
