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
        Schema::create('github_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('installation_id')->unique();
            $table->unsignedBigInteger('account_id');
            $table->string('account_login');
            $table->string('account_type');
            $table->string('repository_selection');
            $table->json('permissions')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('github_installations');
    }
};
