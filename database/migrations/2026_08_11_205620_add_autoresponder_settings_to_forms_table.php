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
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('autoresponder_enabled_override')->nullable()->after('email_subject_override');
            $table->string('autoresponder_subject_override')->nullable()->after('autoresponder_enabled_override');
            $table->text('autoresponder_body_override')->nullable()->after('autoresponder_subject_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['autoresponder_enabled_override', 'autoresponder_subject_override', 'autoresponder_body_override']);
        });
    }
};
