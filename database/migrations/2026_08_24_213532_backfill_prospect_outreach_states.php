<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('prospect_outreach_states')->insertUsing([
            'prospect_id',
            'lifecycle_state',
            'engagement_score',
            'automation_status',
            'sequence_step',
            'initial_email_sent_at',
            'last_outreach_at',
            'next_action_at',
            'stopped_at',
            'stop_reason',
            'created_at',
            'updated_at',
        ], DB::table('prospects')->select([
            'id',
            DB::raw("CASE WHEN status = 'replied' THEN 'replied' WHEN status = 'not_interested' THEN 'not_interested' WHEN status = 'converted' THEN 'customer' WHEN sent_at IS NOT NULL AND lead_temperature = 'hot' THEN 'hot' WHEN sent_at IS NOT NULL AND lead_temperature = 'warm' THEN 'warm' WHEN sent_at IS NOT NULL THEN 'initial_email_sent' WHEN scheduled_send_at IS NOT NULL THEN 'scheduled' WHEN approved_at IS NOT NULL THEN 'approved' WHEN status IN ('researched', 'drafted') THEN 'qualified' ELSE 'new' END"),
            DB::raw("CASE WHEN lead_temperature = 'hot' THEN 10 WHEN lead_temperature = 'warm' THEN 3 ELSE 0 END"),
            DB::raw("CASE WHEN suppressed_at IS NOT NULL OR status IN ('replied', 'converted', 'not_interested') THEN 'stopped' ELSE 'active' END"),
            DB::raw("CASE WHEN sent_at IS NOT NULL THEN 'initial_email' ELSE 'awaiting_initial_email' END"),
            'sent_at',
            'sent_at',
            'next_follow_up_at',
            DB::raw("CASE WHEN suppressed_at IS NOT NULL OR status IN ('replied', 'converted', 'not_interested') THEN COALESCE(suppressed_at, replied_at, converted_at, updated_at) ELSE NULL END"),
            DB::raw("CASE WHEN suppressed_at IS NOT NULL THEN 'suppressed' WHEN status = 'replied' THEN 'replied' WHEN status = 'converted' THEN 'customer' WHEN status = 'not_interested' THEN 'not_interested' ELSE NULL END"),
            'created_at',
            'updated_at',
        ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfilled rows may have changed after deployment and are intentionally retained.
    }
};
