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
        DB::table('website_domains')->orderBy('id')->eachById(function (object $domain): void {
            DB::table('website_domains')->where('id', $domain->id)->update([
                'verified_domain' => str_starts_with($domain->domain, 'www.')
                    ? substr($domain->domain, 4)
                    : $domain->domain,
                'verified_at' => $domain->created_at,
                'verification_method' => 'existing_record',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('website_domains')->update([
            'verified_domain' => null,
            'verified_at' => null,
            'verification_method' => null,
        ]);
    }
};
