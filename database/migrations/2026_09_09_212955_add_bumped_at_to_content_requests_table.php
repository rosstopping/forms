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
            $table->timestamp('bumped_at')->nullable()->after('picked_up_at');
            $table->index(['website_id', 'picked_up_at', 'bumped_at', 'created_at'], 'content_request_queue_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_requests', function (Blueprint $table) {
            $table->dropIndex('content_request_queue_order');
            $table->dropColumn('bumped_at');
        });
    }
};
