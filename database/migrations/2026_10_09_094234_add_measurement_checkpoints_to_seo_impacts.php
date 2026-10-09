<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_impacts', function (Blueprint $table): void {
            $table->json('measurement_checkpoints')->nullable();
        });
        Schema::table('content_generations', function (Blueprint $table): void {
            $table->text('search_performance_property')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seo_impacts', function (Blueprint $table): void {
            $table->dropColumn('measurement_checkpoints');
        });
        Schema::table('content_generations', function (Blueprint $table): void {
            $table->dropColumn('search_performance_property');
        });
    }
};
