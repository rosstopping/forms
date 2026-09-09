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
        Schema::create('backlink_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backlink_audit_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('kind', 20)->default('own');
            $table->text('url');
            $table->string('url_hash', 64);
            $table->unsignedBigInteger('backlinks')->default(0);
            $table->unsignedInteger('referring_domains')->default(0);
            $table->unsignedSmallInteger('page_rank')->nullable();
            $table->string('status', 20)->default('not_fetched');
            $table->json('analysis')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['backlink_audit_id', 'url_hash'], 'backlink_page_unique');
            $table->index(['backlink_audit_id', 'kind', 'backlinks']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backlink_pages');
    }
};
