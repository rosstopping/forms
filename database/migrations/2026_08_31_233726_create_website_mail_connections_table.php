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
        Schema::create('website_mail_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mode')->default('legacy');
            $table->string('status')->default('active');
            $table->text('postmark_server_token')->nullable();
            $table->string('postmark_server_id')->nullable();
            $table->string('postmark_domain_id')->nullable();
            $table->string('sending_domain')->nullable();
            $table->string('dkim_host')->nullable();
            $table->text('dkim_value')->nullable();
            $table->boolean('dkim_verified')->default(false);
            $table->string('return_path_domain')->nullable();
            $table->string('return_path_cname_value')->nullable();
            $table->boolean('return_path_verified')->default(false);
            $table->timestamp('verification_checked_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->string('pause_reason')->nullable();
            $table->unsignedInteger('daily_limit_override')->nullable();
            $table->unsignedInteger('monthly_limit_override')->nullable();
            $table->timestamps();

            $table->index(['mode', 'status']);
            $table->index('sending_domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_mail_connections');
    }
};
