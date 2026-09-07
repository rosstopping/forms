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
        Schema::table('website_domains', function (Blueprint $table) {
            $table->unique('verified_domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_domains', function (Blueprint $table) {
            $table->dropUnique('website_domains_verified_domain_unique');
        });
    }
};
