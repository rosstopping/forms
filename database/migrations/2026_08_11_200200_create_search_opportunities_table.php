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
        Schema::create('search_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained(indexName: 'search_opportunities_website_fk')->cascadeOnDelete();
            $table->foreignId('content_request_id')->nullable()->constrained(indexName: 'search_opportunities_request_fk')->nullOnDelete();
            $table->string('fingerprint', 64);
            $table->string('type')->index();
            $table->string('status')->default('open')->index();
            $table->text('query')->nullable();
            $table->text('page')->nullable();
            $table->string('title');
            $table->text('summary');
            $table->text('recommendation');
            $table->json('metrics');
            $table->decimal('priority_score', 12, 2)->default(0);
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at')->index();
            $table->timestamps();
            $table->unique(['website_id', 'fingerprint'], 'search_opportunities_website_fingerprint_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_opportunities');
    }
};
