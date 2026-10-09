<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('admin_site_access', 20)->default('all')->index();
        });
        Schema::create('staff_website', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'website_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_website');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('admin_site_access');
        });
    }
};
