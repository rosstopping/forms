<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('normalized_name', 40);
            $table->timestamps();
            $table->unique(['website_id', 'normalized_name']);
        });
        Schema::create('form_submission_lead_tag', function (Blueprint $table) {
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['form_submission_id', 'lead_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_lead_tag');
        Schema::dropIfExists('lead_tags');
    }
};
