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
        Schema::create('optimisation_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('optimisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('optimisation_version_id')->constrained()->restrictOnDelete();
            $table->string('action', 16);
            $table->string('method', 32);
            $table->string('status', 16);
            $table->text('message')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index(['optimisation_id', 'performed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('optimisation_deployments');
    }
};
