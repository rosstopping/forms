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
        Schema::create('optimisations', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_health_report_page_id')->nullable()->constrained()->nullOnDelete();
            $table->text('url');
            $table->char('url_hash', 64);
            $table->string('type', 32);
            $table->text('selector')->nullable();
            $table->string('target_description')->nullable();
            $table->string('attribute', 32)->nullable();
            $table->string('status', 32)->index();
            $table->string('deployment_method', 32)->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'status', 'deployment_method'], 'optimisations_payload_lookup_index');
            $table->index(['website_id', 'url_hash'], 'optimisations_url_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('optimisations');
    }
};
