<?php

namespace App\Services;

use App\Models\ContentPlan;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

class CompetitorResearchAutomation
{
    /** @return array{competitors: int, interval_days: int, analyse_pages: int, ranked_keywords: int}|null */
    public function allowance(Website $website): ?array
    {
        if (! $website->is_active || ! $website->owner?->hasActiveMembership()) {
            return null;
        }

        return config('memberships.plans.'.$website->owner->effectiveMembershipTier().'.competitor_research');
    }

    public function pauseReason(?ContentPlan $plan): ?string
    {
        return match (true) {
            ! $plan || $plan->competitor_research_mode === 'manual' => 'Automatic competitor research is switched off.',
            ! $this->allowance($plan->website) => 'Automatic research requires an active Growth or Complete subscription.',
            ! $plan->website->primaryDomain() => 'Add a website domain before researching competitors.',
            ! config('services.dataforseo.login') || ! config('services.dataforseo.password') => 'The research provider needs to be connected by the Sitewell team.',
            default => null,
        };
    }

    public function dispatch(ContentPlan $candidate): int
    {
        return DB::transaction(function () use ($candidate): int {
            $plan = ContentPlan::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($this->pauseReason($plan)) {
                return 0;
            }
            $allowance = $this->allowance($plan->website);
            if ($plan->competitor_researched_at?->greaterThan(now()->subDays($allowance['interval_days']))) {
                return 0;
            }
            $domain = app(CompetitorDomain::class)->normalize($plan->website->primaryDomain()->domain);
            $competitors = $plan->website->competitors()->where('excluded', false)->where('domain', '!=', $domain)
                ->withMax('audits', 'created_at')->orderBy('audits_max_created_at')->orderBy('id')
                ->limit($allowance['competitors'])->get();
            if ($competitors->isEmpty()) {
                return 0;
            }
            $limits = [...config('services.dataforseo.competitor_audits'),
                'ranked_keywords' => $allowance['ranked_keywords'],
                'shared_keywords' => $allowance['ranked_keywords'],
                'missing_keywords' => $allowance['ranked_keywords'],
                'analyse_pages' => $allowance['analyse_pages'],
            ];
            foreach ($competitors as $competitor) {
                app(CompetitorAuditService::class)->request($competitor, $limits, 'scheduled');
            }
            $plan->update(['competitor_researched_at' => now()]);

            return $competitors->count();
        });
    }
}
