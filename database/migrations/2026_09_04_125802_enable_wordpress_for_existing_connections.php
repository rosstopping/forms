<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('websites')
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('wordpress_connections')
                ->whereColumn('wordpress_connections.website_id', 'websites.id'))
            ->update(['wordpress_enabled' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('websites')
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('wordpress_connections')
                ->whereColumn('wordpress_connections.website_id', 'websites.id'))
            ->update(['wordpress_enabled' => false]);
    }
};
