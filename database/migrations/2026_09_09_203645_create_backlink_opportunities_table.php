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
        Schema::create('backlink_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('backlink_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fingerprint', 64);
            $table->string('type', 40);
            $table->string('status', 20)->default('open');
            $table->string('title');
            $table->unsignedSmallInteger('priority_score')->default(0);
            $table->json('evidence');
            $table->timestamps();
            $table->unique(['backlink_audit_id', 'fingerprint'], 'backlink_opportunity_unique');
            $table->index(['website_id', 'fingerprint'], 'backlink_opportunity_deduplication');
        });

        Schema::table('content_requests', function (Blueprint $table) {
            $table->json('backlink_context')->nullable();
            $table->string('backlink_fingerprint', 64)->nullable();
            $table->unique(['website_id', 'backlink_fingerprint'], 'content_backlink_unique');
        });
        Schema::table('content_generations', fn (Blueprint $table) => $table->json('backlink_context')->nullable());
        Schema::table('external_api_usages', fn (Blueprint $table) => $table->foreignId('backlink_audit_id')->nullable()->constrained()->nullOnDelete());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('external_api_usages', fn (Blueprint $table) => $table->dropConstrainedForeignId('backlink_audit_id'));
        Schema::table('content_generations', fn (Blueprint $table) => $table->dropColumn('backlink_context'));
        Schema::table('content_requests', function (Blueprint $table) {
            $table->dropUnique('content_backlink_unique');
            $table->dropColumn(['backlink_context', 'backlink_fingerprint']);
        });
        Schema::dropIfExists('backlink_opportunities');
    }
};
