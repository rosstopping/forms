<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_sdk_test_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id')->unique();
            $table->foreignId('website_repository_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('repository_id');
            $table->unsignedBigInteger('installation_id');
            $table->string('full_name');
            $table->string('base_branch');
            $table->string('base_sha', 40);
            $table->string('tree_sha', 40);
            $table->string('path');
            $table->string('title');
            $table->text('original');
            $table->text('replacement')->nullable();
            $table->string('branch');
            $table->string('commit_sha', 40)->nullable();
            $table->string('status');
            $table->json('usage')->nullable();
            $table->text('error')->nullable();
            $table->text('pull_request_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_sdk_test_runs');
    }
};
