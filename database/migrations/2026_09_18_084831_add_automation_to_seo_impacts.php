<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_impacts', function (Blueprint $table): void {
            $table->foreignId('remediation_run_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('automated')->default(false);
            $table->string('verification_status')->nullable();
            $table->json('verification')->nullable();
            $table->timestamp('next_verification_at')->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->text('automatic_summary')->nullable();
            $table->string('suggested_decision')->nullable();
            $table->timestamp('review_available_at')->nullable()->index();
            $table->timestamp('acknowledged_at')->nullable();
        });
        Schema::table('content_requests', function (Blueprint $table): void {
            $table->string('action_fingerprint', 64)->nullable();
            $table->unique(['website_id', 'action_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('content_requests', function (Blueprint $table): void {
            $table->dropUnique(['website_id', 'action_fingerprint']);
            $table->dropColumn('action_fingerprint');
        });
        Schema::table('seo_impacts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('remediation_run_id');
            $table->dropIndex(['next_verification_at']);
            $table->dropIndex(['review_available_at']);
            $table->dropColumn(['automated', 'verification_status', 'verification', 'next_verification_at', 'verified_at', 'automatic_summary', 'suggested_decision', 'review_available_at', 'acknowledged_at']);
        });
    }
};
