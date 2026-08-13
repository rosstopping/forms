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
        Schema::create('optimisation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('optimisation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('original_value')->nullable();
            $table->longText('new_value');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['optimisation_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('optimisation_versions');
    }
};
