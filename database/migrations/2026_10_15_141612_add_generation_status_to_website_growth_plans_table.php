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
        Schema::table('website_growth_plans', function (Blueprint $table) {
            $table->string('status', 20)->default('completed');
            $table->text('generation_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->index(['website_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_growth_plans', function (Blueprint $table) {
            $table->dropIndex(['website_id', 'status']);
            $table->dropColumn(['status', 'generation_error', 'started_at', 'completed_at']);
        });
    }
};
