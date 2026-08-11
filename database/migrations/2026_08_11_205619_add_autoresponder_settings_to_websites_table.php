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
            $table->boolean('autoresponder_enabled')->default(false)->after('email_recipients');
            $table->string('autoresponder_subject')->nullable()->after('autoresponder_enabled');
            $table->text('autoresponder_body')->nullable()->after('autoresponder_subject');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn(['autoresponder_enabled', 'autoresponder_subject', 'autoresponder_body']);
        });
    }
};
