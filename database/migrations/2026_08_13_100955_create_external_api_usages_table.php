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
        Schema::create('external_api_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->nullable()->constrained(indexName: 'external_api_usages_website_fk')->nullOnDelete();
            $table->foreignId('seo_snapshot_id')->nullable()->constrained(indexName: 'external_api_usages_snapshot_fk')->nullOnDelete();
            $table->string('provider');
            $table->string('endpoint');
            $table->string('request_type');
            $table->unsignedInteger('result_count')->nullable();
            $table->decimal('cost', 12, 6)->nullable();
            $table->string('provider_task_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('requested_at');
            $table->timestamps();

            $table->index(['provider', 'requested_at'], 'external_api_usages_provider_requested_index');
            $table->index(['website_id', 'provider', 'requested_at'], 'external_api_usages_website_provider_requested_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_api_usages');
    }
};
