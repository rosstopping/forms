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
            $table->string('stripe_customer_id')->nullable()->unique()->after('role');
            $table->string('stripe_subscription_id')->nullable()->unique()->after('stripe_customer_id');
            $table->string('membership_tier')->nullable()->index()->after('stripe_subscription_id');
            $table->string('membership_status')->nullable()->index()->after('membership_tier');
            $table->timestamp('membership_current_period_end')->nullable()->after('membership_status');
            $table->timestamp('membership_cancel_at')->nullable()->after('membership_current_period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['stripe_customer_id']);
            $table->dropUnique(['stripe_subscription_id']);
            $table->dropColumn([
                'stripe_customer_id',
                'stripe_subscription_id',
                'membership_tier',
                'membership_status',
                'membership_current_period_end',
                'membership_cancel_at',
            ]);
        });
    }
};
