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
        Schema::table('content_plans', function (Blueprint $table) {
            $table->string('content_engine')->default('copilot');
        });

        Schema::table('content_generations', function (Blueprint $table) {
            $table->string('engine')->nullable();
            $table->string('head_branch')->nullable();
            $table->string('agent_thread_id')->nullable();
            $table->json('agent_usage')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropColumn(['engine', 'head_branch', 'agent_thread_id', 'agent_usage']);
        });

        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropColumn('content_engine');
        });
    }
};
