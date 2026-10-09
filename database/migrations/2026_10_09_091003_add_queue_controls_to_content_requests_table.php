<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_requests', function (Blueprint $table): void {
            $table->timestamp('held_at')->nullable();
            $table->string('hold_reason', 500)->nullable();
            $table->json('dependencies')->nullable();
            $table->json('preflight')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_requests', function (Blueprint $table): void {
            $table->dropColumn(['held_at', 'hold_reason', 'dependencies', 'preflight']);
        });
    }
};
