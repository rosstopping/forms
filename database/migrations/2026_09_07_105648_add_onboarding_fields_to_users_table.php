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
        Schema::table('users', function (Blueprint $table) {
            $table->string('onboarding_status', 30)->nullable()->index()->after('membership_cancel_at');
            $table->timestamp('onboarding_trial_ends_at')->nullable()->after('onboarding_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_status', 'onboarding_trial_ends_at']);
        });
    }
};
