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
        Schema::create('search_console_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained(indexName: 'search_console_metrics_website_fk')->cascadeOnDelete();
            $table->foreignId('search_console_connection_id')->nullable()->constrained(indexName: 'search_console_metrics_connection_fk')->nullOnDelete();
            $table->string('property_url');
            $table->char('property_hash', 64);
            $table->date('month');
            $table->char('dimension_key', 64);
            $table->text('query')->nullable();
            $table->decimal('clicks', 16, 4)->default(0);
            $table->decimal('impressions', 16, 4)->default(0);
            $table->decimal('ctr', 12, 8)->default(0);
            $table->decimal('position', 12, 4)->default(0);
            $table->timestamps();

            $table->unique(['website_id', 'property_hash', 'month', 'dimension_key'], 'search_console_metrics_website_property_month_dimension_unique');
            $table->index(['website_id', 'property_hash', 'dimension_key', 'month'], 'search_console_metrics_history_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_console_metrics');
    }
};
