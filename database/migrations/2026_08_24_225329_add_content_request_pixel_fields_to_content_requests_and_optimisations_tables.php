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
            $table->timestamp('pixel_processed_at')->nullable()->after('picked_up_at');
            $table->text('pixel_error')->nullable()->after('pixel_processed_at');
        });

        Schema::table('optimisations', function (Blueprint $table) {
            $table->foreignId('content_request_id')
                ->nullable()
                ->after('website_health_report_page_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('optimisations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_request_id');
        });

        Schema::table('content_requests', function (Blueprint $table) {
            $table->dropColumn(['pixel_processed_at', 'pixel_error']);
        });
    }
};
