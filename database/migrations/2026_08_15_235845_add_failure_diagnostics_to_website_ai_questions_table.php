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
        Schema::table('website_ai_questions', function (Blueprint $table) {
            $table->string('failure_type')->nullable()->after('error');
            $table->text('failure_detail')->nullable()->after('failure_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_ai_questions', function (Blueprint $table) {
            $table->dropColumn(['failure_type', 'failure_detail']);
        });
    }
};
