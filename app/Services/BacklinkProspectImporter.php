<?php

namespace App\Services;

use App\Models\BacklinkDomainGap;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BacklinkProspectImporter
{
    public function __construct(private PixelUrlNormalizer $urls) {}

    public function import(BacklinkDomainGap $gap, User $user): Prospect
    {
        return DB::transaction(function () use ($gap, $user): Prospect {
            $gap = BacklinkDomainGap::query()->lockForUpdate()->findOrFail($gap->id);
            $alreadyLinked = $gap->prospect_id !== null;
            $prospect = $gap->prospect ?: $this->find($gap->domain);
            if (! $prospect) {
                $prospect = Prospect::query()->create(['user_id' => $user->id, 'business_name' => $gap->domain, 'website_url' => 'https://'.$gap->domain, 'status' => 'new', 'analysis_status' => 'pending', 'prospecting_context' => ['source' => 'backlink_gap', 'domain' => $gap->domain]]);
            }
            $gap->update(['prospect_id' => $prospect->id]);
            if (! $alreadyLinked) {
                $prospect->recordActivity('backlink_opportunity_imported', 'Added to Outreach from a backlink competitor gap.', $user)->update(['metadata' => ['backlink_audit_id' => $gap->backlink_audit_id, 'backlink_domain_gap_id' => $gap->id, 'domain' => $gap->domain, 'domain_rank' => $gap->domain_rank, 'spam_score' => $gap->spam_score, 'competitor_count' => $gap->competitor_count, 'competitor_evidence' => $gap->competitor_evidence, 'collected_at' => $gap->audit->started_at?->toIso8601String(), 'data_source' => 'dataforseo_estimate']]);
            }

            return $prospect;
        }, attempts: 3);
    }

    private function find(string $domain): ?Prospect
    {
        return Prospect::query()->whereNotNull('website_url')->where('website_url', 'like', '%'.$domain.'%')->lockForUpdate()->get()->first(function (Prospect $prospect) use ($domain): bool {
            try {
                return $this->urls->normalizeHost((string) parse_url($prospect->website_url, PHP_URL_HOST)) === $domain;
            } catch (InvalidArgumentException) {
                return false;
            }
        });
    }
}
