<?php

namespace App\Console\Commands;

use App\Models\Website;
use App\Services\GoogleReportingSnapshots;
use Illuminate\Console\Command;
use Throwable;

class SyncReportingSnapshots extends Command
{
    protected $signature = 'reporting:sync {website? : Sitewell website ID}';

    protected $description = 'Save Google index checks and weekly GA4 sessions and active users';

    public function handle(GoogleReportingSnapshots $collector): int
    {
        $id = $this->argument('website');
        if ($id !== null && ! preg_match('/^[1-9][0-9]*$/D', $id)) {
            $this->error('Choose a numeric Sitewell website ID.');

            return self::FAILURE;
        }
        $sites = Website::query()->where('is_active', true)->with('searchConsoleConnection');
        if ($id !== null) {
            $sites->whereKey($id);
        } else {
            $sites->whereHas('searchConsoleConnection', fn ($query) => $query->whereNotNull('property_url'));
        }
        $failed = false;
        $found = false;
        foreach ($sites->lazyById() as $site) {
            if (! $site->searchConsoleConnection?->property_url) {
                continue;
            }
            $found = true;
            $sources = ['indexing' => $site->searchConsoleConnection?->property_url, 'analytics' => config('reporting.analytics_properties.'.$site->id)];
            foreach ($sources as $source => $property) {
                if ($property === null || $property === '') {
                    continue;
                }
                try {
                    $collector->{$source}($site, (string) $property);
                } catch (Throwable) {
                    $failed = true;
                    $this->error("Website {$site->id}: {$source} sync failed. Check the property and Google permissions.");
                }
            }
        }
        if ($id !== null && ! $found) {
            $this->error('Choose an active website with a connected Search Console property.');

            return self::FAILURE;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
