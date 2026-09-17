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
        Schema::table('website_repositories', function (Blueprint $table) {
            $table->index(['github_installation_id', 'repository_id'], 'website_repositories_installation_repository_index');
        });

        Schema::table('website_repositories', function (Blueprint $table) {
            $table->dropUnique(['github_installation_id', 'repository_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_repositories', function (Blueprint $table) {
            $table->unique(['github_installation_id', 'repository_id']);
        });

        Schema::table('website_repositories', function (Blueprint $table) {
            $table->dropIndex('website_repositories_installation_repository_index');
        });
    }
};
