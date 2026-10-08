<?php

use App\Jobs\CreateGoogleAdsCampaign;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $connection = DB::table('google_ads_connections as connections')
                ->join('website_domains as domains', 'domains.website_id', '=', 'connections.website_id')
                ->where('domains.domain', 'sitewell.digizu.co.uk')
                ->where('domains.is_primary', true)
                ->where('domains.ownership_status', 'verified')
                ->where('connections.customer_id', '8204189823')
                ->where('connections.currency_code', 'GBP')
                ->select('connections.id', 'connections.website_id', 'connections.customer_id', 'connections.connected_by')
                ->first();

            if ($connection === null) {
                return;
            }

            $requestKey = 'c20ac3a1-f00d-4a67-88cf-0e3abc773ec8';
            if (DB::table('google_ads_campaign_drafts')->where('request_key', $requestKey)->exists()) {
                return;
            }

            $draftId = DB::table('google_ads_campaign_drafts')->insertGetId([
                'website_id' => $connection->website_id,
                'google_ads_connection_id' => $connection->id,
                'created_by' => $connection->connected_by,
                'request_key' => $requestKey,
                'customer_id' => $connection->customer_id,
                'name' => 'Sitewell | Managed SEO | UK',
                'daily_budget_micros' => 20_000_000,
                'max_cpc_micros' => 3_000_000,
                'city_name' => 'United Kingdom',
                'country_code' => 'GB',
                'radius_miles' => 0,
                'target_country' => 'GB',
                'final_url' => 'https://sitewell.digizu.co.uk/managed-seo?utm_source=google&utm_medium=cpc&utm_campaign=sitewell_service_search',
                'keywords' => json_encode([
                    'seo services for small business', 'seo for small businesses',
                    'small business seo services', 'managed seo services',
                    'seo management services', 'website seo services',
                    'local seo services', 'seo company for small business',
                ], JSON_THROW_ON_ERROR),
                'negative_keywords' => json_encode([
                    'website checker', 'website speed test', 'ahrefs', 'dr checker',
                    'domain authority checker', 'free seo checker', 'on page seo checker',
                    'seo test', 'website verifier',
                ], JSON_THROW_ON_ERROR),
                'headlines' => json_encode([
                    'SEO For Small Businesses', 'We Do The Website Work',
                    'Fully Managed SEO', 'Help More Customers Find You',
                    'Your Website, Looked After', 'Start With A Free Review',
                    'A Personal Review From Ross', 'Website Fixes And SEO',
                    'No Long-Term Contract', 'Practical SEO Help',
                    'Google And AI Visibility', 'A Real Person Doing The Work',
                ], JSON_THROW_ON_ERROR),
                'descriptions' => json_encode([
                    'We improve your website and help more customers find it. Start with a free website review.',
                    'Ross reviews your website, agrees the priorities and handles the SEO work for you.',
                    'Website fixes, useful content and ongoing SEO. See the work in clear weekly updates.',
                    'You run your business. We look after your website. No long-term contract.',
                ], JSON_THROW_ON_ERROR),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            CreateGoogleAdsCampaign::dispatch($draftId)->afterCommit();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /** Campaign creation is external; preserve its ledger and manage rollback in Ads. */
    }
};
