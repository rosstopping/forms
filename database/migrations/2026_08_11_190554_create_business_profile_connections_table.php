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
        Schema::create('business_profile_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->unique()->constrained(indexName: 'bp_connections_website_fk')->cascadeOnDelete();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'bp_connections_connector_fk')->nullOnDelete();
            $table->string('account_name')->nullable();
            $table->string('location_name')->nullable();
            $table->string('location_title')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->boolean('weekly_audits_enabled')->default(true)->index();
            $table->boolean('weekly_posts_enabled')->default(false)->index();
            $table->unsignedTinyInteger('post_weekday')->default(1);
            $table->unsignedTinyInteger('post_hour')->default(9);
            $table->string('timezone')->default('Europe/London');
            $table->text('brand_guidance')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_profile_connections');
    }
};
