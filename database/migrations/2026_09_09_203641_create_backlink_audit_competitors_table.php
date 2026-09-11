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
        Schema::create('backlink_audit_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backlink_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_competitor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain');
            $table->timestamps();
            $table->unique(['backlink_audit_id', 'domain'], 'backlink_audit_competitor_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backlink_audit_competitors');
    }
};
