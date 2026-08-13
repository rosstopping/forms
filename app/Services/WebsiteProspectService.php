<?php

namespace App\Services;

use App\Jobs\AnalyzeProspect;
use App\Models\Prospect;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

class WebsiteProspectService
{
    public function find(Website $website): ?Prospect
    {
        $linkedProspect = Prospect::query()->where('website_id', $website->id)->first();

        if ($linkedProspect) {
            return $linkedProspect;
        }

        $domain = $website->primaryDomain()?->domain;

        if (blank($domain)) {
            return null;
        }

        $normalizedDomain = $this->normalizeHost($domain);

        return Prospect::query()
            ->whereNull('website_id')
            ->where('website_url', 'like', '%'.$normalizedDomain.'%')
            ->get()
            ->first(fn (Prospect $prospect): bool => $this->normalizeHost((string) parse_url($prospect->website_url, PHP_URL_HOST)) === $normalizedDomain);
    }

    public function createOrLink(Website $website, User $user): Prospect
    {
        return DB::transaction(function () use ($website, $user): Prospect {
            $lockedWebsite = Website::query()->lockForUpdate()->findOrFail($website->id);
            $prospect = $this->find($lockedWebsite);

            if ($prospect) {
                if ($prospect->website_id === null) {
                    $prospect->update(['website_id' => $lockedWebsite->id]);
                    $prospect->recordActivity('website_linked', 'Linked to the existing Sitewell website.', $user);
                }

                return $prospect;
            }

            $domain = $lockedWebsite->primaryDomain()?->domain;
            abort_if(blank($domain), 422, 'Add a domain to this website before creating an outreach prospect.');

            $prospect = Prospect::query()->create([
                'user_id' => $user->id,
                'website_id' => $lockedWebsite->id,
                'business_name' => $lockedWebsite->name,
                'website_url' => 'https://'.$domain,
            ]);
            $prospect->recordActivity('created', 'Created from the Sitewell website and queued for research.', $user);
            AnalyzeProspect::dispatch($prospect)->afterCommit();

            return $prospect;
        });
    }

    protected function normalizeHost(string $host): string
    {
        return strtolower(preg_replace('/^www\./i', '', trim($host)) ?? '');
    }
}
