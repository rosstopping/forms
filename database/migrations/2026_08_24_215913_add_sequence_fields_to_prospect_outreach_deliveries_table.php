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
        Schema::table('prospect_outreach_deliveries', function (Blueprint $table) {
            $table->string('message_type', 40)->default('initial')->after('recipient_email')->index();
            $table->string('idempotency_key')->nullable()->after('message_type')->unique();
            $table->string('status', 20)->default('pending')->after('idempotency_key')->index();
            $table->string('subject')->nullable()->after('status');
            $table->text('body')->nullable()->after('subject');
            $table->timestamp('failed_at')->nullable()->after('sent_at');
            $table->text('failure_reason')->nullable()->after('failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospect_outreach_deliveries', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex(['message_type']);
            $table->dropIndex(['status']);
            $table->dropColumn(['message_type', 'idempotency_key', 'status', 'subject', 'body', 'failed_at', 'failure_reason']);
        });
    }
};
