<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submission_follow_up_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->timestamp('due_at');
            $table->string('status', 20)->default('pending');
            $table->string('recipient')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_at'], 'follow_up_reminders_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_follow_up_reminders');
    }
};
