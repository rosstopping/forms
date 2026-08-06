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
        Schema::create('website_health_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('overall_status')->nullable();
            $table->unsignedSmallInteger('passed_checks')->default(0);
            $table->unsignedSmallInteger('warning_checks')->default(0);
            $table->unsignedSmallInteger('failed_checks')->default(0);
            $table->json('categories')->nullable();
            $table->json('checks')->nullable();
            $table->json('metrics')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'status', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_health_reports');
    }
};
