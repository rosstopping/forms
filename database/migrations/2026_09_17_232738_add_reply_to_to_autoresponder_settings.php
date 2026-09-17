<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->string('autoresponder_reply_to_email')->nullable();
        });
        Schema::table('forms', function (Blueprint $table): void {
            $table->string('autoresponder_reply_to_email_override')->nullable();
        });
        Schema::table('form_submission_email_deliveries', function (Blueprint $table): void {
            $table->string('reply_to_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropColumn('autoresponder_reply_to_email');
        });
        Schema::table('forms', function (Blueprint $table): void {
            $table->dropColumn('autoresponder_reply_to_email_override');
        });
        Schema::table('form_submission_email_deliveries', function (Blueprint $table): void {
            $table->dropColumn('reply_to_email');
        });
    }
};
