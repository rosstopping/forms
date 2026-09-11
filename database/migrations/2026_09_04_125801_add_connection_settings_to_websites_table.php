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
        Schema::table('websites', function (Blueprint $table) {
            $table->boolean('wordpress_enabled')->default(false)->after('pixel_enabled');
            $table->boolean('pixel_enabled')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->boolean('pixel_enabled')->default(true)->change();
            $table->dropColumn('wordpress_enabled');
        });
    }
};
