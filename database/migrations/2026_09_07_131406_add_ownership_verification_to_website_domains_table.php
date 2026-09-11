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
            $table->dropUnique('website_domains_domain_unique');
            $table->string('ownership_status', 20)->default('verified')->index()->after('is_primary');
            $table->string('verified_domain')->nullable()->after('ownership_status');
            $table->timestamp('verified_at')->nullable()->after('verified_domain');
            $table->string('verification_method', 30)->nullable()->after('verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_domains', function (Blueprint $table) {
            $table->dropColumn(['ownership_status', 'verified_domain', 'verified_at', 'verification_method']);
            $table->unique('domain');
        });
    }
};
