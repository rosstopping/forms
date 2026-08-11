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
        Schema::table('remediation_runs', function (Blueprint $table) {
            $table->uuid('copilot_task_id')->nullable()->unique()->after('status');
            $table->text('copilot_task_url')->nullable()->after('copilot_task_id');
            $table->string('copilot_task_state')->nullable()->after('copilot_task_url');
            $table->text('prompt')->nullable()->after('findings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('remediation_runs', function (Blueprint $table) {
            $table->dropUnique(['copilot_task_id']);
            $table->dropColumn([
                'copilot_task_id',
                'copilot_task_url',
                'copilot_task_state',
                'prompt',
            ]);
        });
    }
};
