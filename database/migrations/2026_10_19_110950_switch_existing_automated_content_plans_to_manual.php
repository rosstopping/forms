<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('content_plans')
            ->whereIn('content_engine', ['copilot', 'amp'])
            ->update([
                'content_engine' => 'manual',
                'enabled' => false,
                'updated_at' => now(),
            ]);
    }

    /**
     * Keep manual settings on rollback: the previous engine cannot be recovered,
     * and rolling back must not silently re-enable paid automation.
     */
    public function down(): void {}
};
