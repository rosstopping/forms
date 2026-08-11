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
        Schema::create('user_website', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('viewer');
            $table->timestamps();

            $table->unique(['user_id', 'website_id'], 'uw_user_website_unique');
            $table->index(['website_id', 'role'], 'uw_website_role_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_website');
    }
};
