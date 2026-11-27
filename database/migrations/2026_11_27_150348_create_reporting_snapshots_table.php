<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('source', 16);
            $table->text('property');
            $table->char('property_hash', 64);
            $table->date('period_start');
            $table->date('period_end');
            $table->json('metrics');
            $table->timestamps();
            $table->unique(['website_id', 'source', 'property_hash', 'period_end'], 'reporting_snapshot_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_snapshots');
    }
};
