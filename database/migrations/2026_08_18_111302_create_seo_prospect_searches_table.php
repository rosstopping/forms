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
        Schema::create('seo_prospect_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('industry');
            $table->string('location');
            $table->json('service_keywords');
            $table->json('keywords');
            $table->unsignedSmallInteger('minimum_position')->default(20);
            $table->unsignedSmallInteger('maximum_position')->default(100);
            $table->unsignedSmallInteger('maximum_pages')->default(20);
            $table->string('provider', 40)->default('dataforseo');
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('candidate_count')->default(0);
            $table->unsignedInteger('suitable_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->decimal('api_cost', 12, 6)->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_prospect_searches');
    }
};
