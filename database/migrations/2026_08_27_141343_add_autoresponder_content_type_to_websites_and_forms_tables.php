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
            $table->string('autoresponder_content_type')->default('text')->after('autoresponder_body');
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->string('autoresponder_content_type_override')->nullable()->after('autoresponder_body_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('autoresponder_content_type_override');
        });

        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn('autoresponder_content_type');
        });
    }
};
