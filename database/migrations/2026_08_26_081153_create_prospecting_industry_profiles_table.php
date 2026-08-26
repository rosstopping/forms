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
        Schema::create('prospecting_industry_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedSmallInteger('priority')->default(0)->index();
            $table->unsignedInteger('estimated_customer_value');
            $table->string('customer_value_band', 20);
            $table->json('service_keywords');
            $table->json('search_keywords');
            $table->unsignedSmallInteger('minimum_position')->default(8);
            $table->unsignedSmallInteger('maximum_position')->default(50);
            $table->unsignedSmallInteger('maximum_site_size')->default(30);
            $table->unsignedTinyInteger('automatic_import_score')->default(65);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospecting_industry_profiles');
    }
};
