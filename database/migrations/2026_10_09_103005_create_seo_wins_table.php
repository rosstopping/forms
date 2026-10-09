<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_wins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seo_target_keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->char('fingerprint', 64);
            $table->string('rule', 50);
            $table->unsignedSmallInteger('rule_version')->default(1);
            $table->string('importance', 20);
            $table->string('confidence', 20);
            $table->string('title');
            $table->json('evidence');
            $table->timestamp('observed_at');
            $table->timestamp('confirmed_at');
            $table->text('client_draft');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shared_at')->nullable();
            $table->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('shared_text')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
            $table->unique(['website_id', 'fingerprint']);
            $table->index(['website_id', 'confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_wins');
    }
};
