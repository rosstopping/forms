<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_local_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('host', 100)->default('local');
            $table->string('path', 1000);
            $table->string('codex_project_id')->nullable();
            $table->timestamps();
            $table->unique(['website_id', 'host']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_local_projects');
    }
};
