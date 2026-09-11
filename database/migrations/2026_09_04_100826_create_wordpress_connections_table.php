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
        Schema::create('wordpress_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('public_id', 40)->unique();
            $table->string('pairing_code_hash', 64)->nullable()->index();
            $table->timestamp('pairing_code_expires_at')->nullable();
            $table->string('credential_hash', 64)->nullable();
            $table->text('wordpress_url')->nullable();
            $table->string('plugin_version', 50)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wordpress_connections');
    }
};
