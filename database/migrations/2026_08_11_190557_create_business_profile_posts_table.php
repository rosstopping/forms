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
        Schema::create('business_profile_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_connection_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('generating')->index();
            $table->text('topic')->nullable();
            $table->text('summary')->nullable();
            $table->string('call_to_action_type')->nullable();
            $table->text('call_to_action_url')->nullable();
            $table->string('google_post_name')->nullable()->unique();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_profile_posts');
    }
};
