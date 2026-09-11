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
        Schema::create('backlink_domain_gaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backlink_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain');
            $table->unsignedSmallInteger('domain_rank')->nullable();
            $table->unsignedSmallInteger('spam_score')->nullable();
            $table->unsignedTinyInteger('competitor_count')->default(0);
            $table->json('competitor_evidence');
            $table->unsignedSmallInteger('priority_score')->default(0);
            $table->timestamps();
            $table->unique(['backlink_audit_id', 'domain'], 'backlink_domain_gap_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backlink_domain_gaps');
    }
};
