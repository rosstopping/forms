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
        Schema::create('backlink_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backlink_audit_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('state', 20)->default('live');
            $table->string('source_domain');
            $table->text('source_url');
            $table->text('target_url');
            $table->text('anchor')->nullable();
            $table->boolean('dofollow')->nullable();
            $table->boolean('broken')->default(false);
            $table->unsignedSmallInteger('source_domain_rank')->nullable();
            $table->unsignedSmallInteger('source_page_rank')->nullable();
            $table->unsignedSmallInteger('spam_score')->nullable();
            $table->unsignedInteger('links_count')->default(1);
            $table->string('semantic_location', 80)->nullable();
            $table->timestamp('first_seen')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
            $table->unique(['backlink_audit_id', 'fingerprint'], 'backlink_link_unique');
            $table->index(['backlink_audit_id', 'state', 'broken']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backlink_links');
    }
};
