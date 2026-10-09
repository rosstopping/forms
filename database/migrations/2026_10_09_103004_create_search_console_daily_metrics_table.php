<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_console_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('search_console_connection_id')->constrained(indexName: 'search_daily_connection_fk')->cascadeOnDelete();
            $table->text('property_url');
            $table->char('property_hash', 64);
            $table->date('date');
            $table->string('data_status', 20);
            $table->unsignedBigInteger('clicks')->nullable();
            $table->unsignedBigInteger('impressions')->nullable();
            $table->double('position')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->unique(['website_id', 'property_hash', 'date'], 'search_daily_property_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_daily_metrics');
    }
};
