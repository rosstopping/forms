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
            $table->timestamp('pixel_last_seen_at')->nullable();
            $table->text('pixel_last_seen_url')->nullable();
            $table->string('pixel_last_seen_hostname')->nullable();
            $table->string('pixel_version', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn([
                'pixel_last_seen_at',
                'pixel_last_seen_url',
                'pixel_last_seen_hostname',
                'pixel_version',
            ]);
        });
    }
};
