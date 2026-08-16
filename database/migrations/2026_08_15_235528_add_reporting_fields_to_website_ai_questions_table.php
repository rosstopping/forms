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
            $table->text('report_reason')->nullable()->after('error');
            $table->timestamp('reported_at')->nullable()->index()->after('report_reason');
            $table->timestamp('credited_at')->nullable()->after('reported_at');
            $table->foreignId('credited_by_user_id')->nullable()->after('credited_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_ai_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credited_by_user_id');
            $table->dropColumn(['report_reason', 'reported_at', 'credited_at']);
        });
    }
};
