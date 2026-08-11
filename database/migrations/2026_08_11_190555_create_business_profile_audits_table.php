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
        Schema::create('business_profile_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_connection_id')->constrained(indexName: 'bp_audits_connection_fk')->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('overall_status')->nullable()->index();
            $table->json('snapshot')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_profile_audits');
    }
};
