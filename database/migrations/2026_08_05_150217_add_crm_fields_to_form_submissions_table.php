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
            $table->string('status')->default('new')->after('is_spam');
            $table->text('notes')->nullable()->after('status');
            $table->foreignId('assigned_to')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['notes', 'status']);
        });
    }
};
