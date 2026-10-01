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
        Schema::create('google_ads_campaign_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('google_ads_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_key')->unique();
            $table->string('customer_id', 10);
            $table->string('name');
            $table->unsignedBigInteger('daily_budget_micros');
            $table->string('city_name');
            $table->string('country_code', 2)->default('GB');
            $table->unsignedTinyInteger('radius_miles');
            $table->string('final_url', 2048);
            $table->json('keywords');
            $table->json('headlines');
            $table->json('descriptions');
            $table->string('status', 20)->default('pending')->index();
            $table->string('campaign_resource_name')->nullable()->unique();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_ads_campaign_drafts');
    }
};
