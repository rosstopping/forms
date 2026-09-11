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
        Schema::table('wordpress_connections', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('credential_hash');
            $table->string('active_release_public_id', 40)->nullable()->after('plugin_version');
            $table->timestamp('last_deployed_at')->nullable()->after('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wordpress_connections', function (Blueprint $table) {
            $table->dropColumn(['webhook_secret', 'active_release_public_id', 'last_deployed_at']);
        });
    }
};
