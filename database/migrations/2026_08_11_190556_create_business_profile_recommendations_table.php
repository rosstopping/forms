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
        Schema::create('business_profile_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_audit_id')->constrained(indexName: 'bp_recommendations_audit_fk')->cascadeOnDelete();
            $table->string('key');
            $table->string('severity')->default('warning');
            $table->string('title');
            $table->text('description');
            $table->string('field_mask')->nullable();
            $table->json('current_value')->nullable();
            $table->json('proposed_value')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users', indexName: 'bp_recommendations_approver_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['business_profile_audit_id', 'key'], 'bp_recommendations_audit_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_profile_recommendations');
    }
};
