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
        Schema::table('websites', function (Blueprint $table): void {
            $table->string('service_package')->nullable();
            $table->string('service_status')->nullable()->index();
            $table->timestamp('service_ends_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropIndex(['service_status']);
            $table->dropColumn(['service_package', 'service_status', 'service_ends_at']);
        });
    }
};
