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
        Schema::create('form_submission_email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_mail_connection_id')
                ->nullable()
                ->constrained(indexName: 'form_email_deliveries_mail_connection_fk')
                ->nullOnDelete();
            $table->string('type')->default('autoresponder');
            $table->string('mode')->default('legacy');
            $table->string('status')->default('queued');
            $table->string('recipient');
            $table->string('subject');
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('suppression_reason')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['form_submission_id', 'type']);
            $table->index(['website_id', 'status', 'sent_at']);
            $table->index(['website_id', 'recipient', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_submission_email_deliveries');
    }
};
