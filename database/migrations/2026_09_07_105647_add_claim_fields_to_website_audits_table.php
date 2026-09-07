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
        Schema::table('website_audits', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('website_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('email')->nullable()->after('domain')->index();
            $table->timestamp('claim_email_sent_at')->nullable()->after('completed_at');
            $table->timestamp('claimed_at')->nullable()->after('claim_email_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_audits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('website_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['email', 'claim_email_sent_at', 'claimed_at']);
        });
    }
};
