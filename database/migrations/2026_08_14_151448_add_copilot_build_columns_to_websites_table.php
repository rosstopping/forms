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
            $table->uuid('copilot_build_task_id')->nullable()->unique();
            $table->text('copilot_build_task_url')->nullable();
            $table->string('copilot_build_task_state')->nullable();
            $table->longText('copilot_build_prompt')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropUnique(['copilot_build_task_id']);
            $table->dropColumn([
                'copilot_build_task_id',
                'copilot_build_task_url',
                'copilot_build_task_state',
                'copilot_build_prompt',
            ]);
        });
    }
};
