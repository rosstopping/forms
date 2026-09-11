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
        Schema::table('website_repositories', function (Blueprint $table): void {
            $table->string('wordpress_workflow_path')->nullable();
            $table->string('wordpress_artifact_name')->nullable();
        });

        Schema::table('wordpress_static_releases', function (Blueprint $table): void {
            $table->unsignedBigInteger('github_workflow_run_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wordpress_static_releases', function (Blueprint $table): void {
            $table->dropColumn('github_workflow_run_id');
        });

        Schema::table('website_repositories', function (Blueprint $table): void {
            $table->dropColumn(['wordpress_workflow_path', 'wordpress_artifact_name']);
        });
    }
};
