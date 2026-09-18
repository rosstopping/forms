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
        Schema::table('search_console_connections', function (Blueprint $table) {
            $table->timestamp('access_denied_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('search_console_connections', function (Blueprint $table) {
            $table->dropColumn('access_denied_at');
        });
    }
};
