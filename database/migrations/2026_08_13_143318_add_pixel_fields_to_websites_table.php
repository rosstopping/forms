<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('pixel_public_key', 40)->nullable()->unique()->after('id');
            $table->boolean('pixel_enabled')->default(true)->after('pixel_public_key');
            $table->unsignedBigInteger('pixel_payload_version')->default(1)->after('pixel_enabled');
        });

        DB::table('websites')->orderBy('id')->eachById(function (object $website): void {
            DB::table('websites')->where('id', $website->id)->update([
                'pixel_public_key' => 'sw_'.Str::lower(Str::random(28)),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn(['pixel_public_key', 'pixel_enabled', 'pixel_payload_version']);
        });
    }
};
