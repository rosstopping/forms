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
        Schema::table('content_requests', function (Blueprint $table) {
            $table->foreignId('manual_taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manual_started_at')->nullable()->index();
            $table->timestamp('manual_completed_at')->nullable();
            $table->text('manual_prompt')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manual_taken_by');
            $table->dropColumn(['manual_started_at', 'manual_completed_at', 'manual_prompt']);
        });
    }
};
