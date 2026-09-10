<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->json('additional_weekdays')->nullable();
        });
        Schema::table('content_generations', function (Blueprint $table) {
            $table->string('trigger')->default('legacy');
            $table->text('skip_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropColumn(['trigger', 'skip_reason']);
        });
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropColumn('additional_weekdays');
        });
    }
};
