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
        Schema::create('website_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('github_installation_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('repository_id');
            $table->string('full_name');
            $table->string('default_branch');
            $table->boolean('private')->default(true);
            $table->json('permissions')->nullable();
            $table->string('project_path')->nullable();
            $table->timestamps();

            $table->unique(['github_installation_id', 'repository_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_repositories');
    }
};
