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
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->timestamp('autoresponder_sent_at')->nullable()->after('email_error');
            $table->timestamp('autoresponder_failed_at')->nullable()->after('autoresponder_sent_at');
            $table->text('autoresponder_error')->nullable()->after('autoresponder_failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn(['autoresponder_sent_at', 'autoresponder_failed_at', 'autoresponder_error']);
        });
    }
};
