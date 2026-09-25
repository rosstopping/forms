<?php

namespace App\Services;

use App\Models\SeoTargetKeyword;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteSetup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WebsiteSetupService
{
    public function __construct(private ContentSchedule $schedule, private CompetitorDomain $domains) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Website
    {
        return DB::transaction(function () use ($data): Website {
            $website = Website::query()->create([
                'name' => $data['name'], 'user_id' => $data['manager_id'] ?? null,
                'is_active' => true, 'auto_discovered' => false,
                'email_enabled' => false, 'email_recipients' => [], 'webhook_enabled' => false,
                'health_reports_enabled' => false, 'weekly_ranking_reports_enabled' => false,
                'seo_weekly_snapshots_enabled' => false,
            ]);
            $website->domains()->create(['domain' => $data['domain'], 'is_primary' => true]);
            if ($website->user_id) {
                $website->members()->attach($website->user_id, ['role' => Website::MEMBER_ROLE_MANAGER]);
            }
            unset($data['domain'], $data['manager_id']);
            $this->save($website, 'business', $data);

            return $website;
        });
    }

    /** @param array<string, mixed> $data */
    public function save(Website $website, string $step, array $data): WebsiteSetup
    {
        return DB::transaction(function () use ($website, $step, $data): WebsiteSetup {
            Website::query()->whereKey($website)->lockForUpdate()->firstOrFail();
            $setup = WebsiteSetup::query()->firstOrCreate(['website_id' => $website->id]);
            unset($data['navigation']);
            if ($step === 'delivery') {
                $data['additional_weekdays'] = array_map(intval(...), $data['additional_weekdays'] ?? []);
            }
            $steps = array_keys(WebsiteSetup::STEPS);
            $setup->update([
                'data' => [...($setup->data ?? []), $step => $data],
                'saved_steps' => array_values(array_unique([...($setup->saved_steps ?? []), $step])),
                'current_step' => $steps[array_search($step, $steps, true) + 1] ?? 'review',
                'completed_at' => null,
            ]);

            return $setup;
        });
    }

    /** @return array<string, array<string, mixed>> */
    public function defaults(Website $website): array
    {
        $plan = $website->contentPlan;

        return [
            'business' => ['name' => $website->name, 'audience' => $plan?->audience],
            'goals' => [],
            'connection' => ['method' => $website->wordpress_enabled ? 'wordpress' : ($website->repository ? 'github' : 'later')],
            'google' => ['local_business' => (bool) $website->businessProfileConnection],
            'targets' => ['keywords' => '', 'competitors' => ''],
            'content' => ['language' => 'British English', 'tone' => 'Clear, friendly and direct', 'guidance' => $plan?->guidance],
            'delivery' => [
                'enabled' => $plan?->enabled ?? false, 'weekday' => $plan?->weekday ?? 1,
                'additional_weekdays' => $plan?->additional_weekdays ?? [], 'hour' => $plan?->hour ?? 8,
                'timezone' => $plan?->timezone ?? 'Europe/London',
                'competitor_research_mode' => $plan?->competitor_research_mode ?? 'manual',
                'health_reports_enabled' => $website->health_reports_enabled,
                'weekly_ranking_reports_enabled' => $website->weekly_ranking_reports_enabled,
                'email_enabled' => $website->email_enabled,
                'email_recipients' => implode(', ', $website->email_recipients ?? []),
            ],
        ];
    }

    public function finish(Website $website, User $admin): void
    {
        DB::transaction(function () use ($website, $admin): void {
            $website = Website::query()->whereKey($website)->lockForUpdate()->firstOrFail();
            $setup = WebsiteSetup::query()->where('website_id', $website->id)->first();
            if ($setup?->completed_at) {
                return;
            }
            $missing = array_diff(array_keys(WebsiteSetup::STEPS), ['review'], $setup?->saved_steps ?? []);
            if ($missing !== []) {
                throw ValidationException::withMessages(['confirm' => 'Save each setup step before finishing: '.implode(', ', array_map(fn (string $step): string => WebsiteSetup::STEPS[$step], $missing)).'.']);
            }
            $data = $setup->data;
            $delivery = $data['delivery'];
            $limit = $this->schedule->weeklyLimit($website);
            if (($delivery['enabled'] || $delivery['competitor_research_mode'] !== 'manual') && $limit === 0) {
                throw ValidationException::withMessages(['confirm' => 'Scheduled content and competitor research need an active Growth or Complete client membership. Leave them off to finish setup now.']);
            }
            if ($delivery['enabled'] && count($delivery['additional_weekdays']) + 1 > $limit) {
                throw ValidationException::withMessages(['confirm' => "This client’s membership allows {$limit} scheduled content improvements per week. Update the schedule."]);
            }
            if (($delivery['health_reports_enabled'] || $delivery['weekly_ranking_reports_enabled']) && ! $website->owner?->hasActiveMembership()) {
                throw ValidationException::withMessages(['confirm' => 'Scheduled reports need an active client membership. Leave them off to finish setup now.']);
            }
            $terms = collect($this->lines($data['targets']['keywords'] ?? ''))
                ->mapWithKeys(fn (string $term): array => [SeoTargetKeyword::normalize($term) => $term]);
            $existing = $website->seoTargetKeywords()->pluck('normalized_term');
            $newTerms = $terms->except($existing->all());
            if ($website->seoTargetKeywords()->whereNull('archived_at')->count() + $newTerms->count() > 20) {
                throw ValidationException::withMessages(['confirm' => 'This would exceed 20 active keywords. Remove some new keywords or archive existing ones.']);
            }
            $guidance = $this->guidance($data);
            if (mb_strlen($guidance) > 4500) {
                throw ValidationException::withMessages(['confirm' => 'The combined business, goals and content brief is '.number_format(mb_strlen($guidance)).' characters. Shorten it to 4,500 characters so all guidance reaches content generation.']);
            }
            $website->update([
                'name' => $data['business']['name'],
                'email_enabled' => $delivery['email_enabled'],
                'email_recipients' => array_values(array_unique(preg_split('/[\s,;]+/', trim($delivery['email_recipients'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))),
                'health_reports_enabled' => $delivery['health_reports_enabled'],
                'weekly_ranking_reports_enabled' => $delivery['weekly_ranking_reports_enabled'],
            ]);
            if ($data['connection']['method'] === 'wordpress') {
                $website->update(['wordpress_enabled' => true]);
            }
            if ($data['connection']['method'] === 'pixel' && config('forms.pixel_ui_enabled')) {
                $website->update(['pixel_enabled' => true]);
            }
            $plan = $website->contentPlan()->firstOrNew();
            $plan->fill([
                'created_by' => $plan->created_by ?: $admin->id,
                'audience' => $data['business']['audience'], 'guidance' => $guidance,
                'enabled' => $delivery['enabled'], 'weekday' => $delivery['weekday'],
                'additional_weekdays' => $delivery['additional_weekdays'],
                'hour' => $delivery['hour'], 'timezone' => $delivery['timezone'],
                'competitor_research_mode' => $delivery['competitor_research_mode'],
            ]);
            $plan->setRelation('website', $website);
            if ($plan->enabled && ($reason = $this->schedule->pauseReason($plan))) {
                throw ValidationException::withMessages(['confirm' => $reason.' Leave scheduled content off to finish setup now.']);
            }
            if ($plan->enabled && $website->repository?->installation?->status !== 'active') {
                throw ValidationException::withMessages(['confirm' => 'Reconnect the GitHub installation before enabling scheduled content.']);
            }
            $plan->save();
            $website->seoTargetKeywords()->createMany($newTerms->map(fn (string $term): array => ['term' => $term, 'priority' => SeoTargetKeyword::PRIORITY_NORMAL])->values()->all());
            foreach ($this->lines($data['targets']['competitors'] ?? '') as $competitor) {
                $domain = $this->domains->normalize($competitor);
                if ($website->domains->contains(fn ($own): bool => $this->domains->normalize($own->domain) === $domain)) {
                    throw ValidationException::withMessages(['confirm' => 'A competitor matches this website’s domain. Update the competitor list.']);
                }
                $website->competitors()->firstOrCreate(['domain' => $domain]);
            }
            $setup->update(['completed_at' => now(), 'current_step' => 'review']);
        });
    }

    /** @param array<string, array<string, mixed>> $data */
    private function guidance(array $data): string
    {
        $fields = [
            'Business' => data_get($data, 'business.name'),
            'Services' => data_get($data, 'business.services'),
            'Locations served' => data_get($data, 'business.locations'),
            'What makes this business different' => data_get($data, 'business.difference'),
            'Business objective' => data_get($data, 'goals.objective'),
            'Priority services' => data_get($data, 'goals.priority_services'),
            'Work to avoid' => data_get($data, 'goals.avoid'),
            'Success measure' => data_get($data, 'goals.success_measure'),
            'Language and spelling' => data_get($data, 'content.language'),
            'Tone' => data_get($data, 'content.tone'),
            'Verified business facts' => data_get($data, 'content.facts'),
            'Writing examples' => data_get($data, 'content.examples'),
            'Words, claims and topics to avoid' => data_get($data, 'content.avoid'),
            'Additional writing guidance' => data_get($data, 'content.guidance'),
        ];

        return collect($fields)->filter(fn ($value): bool => filled($value))
            ->map(fn (string $value, string $label): string => $label.":\n".$value)->implode("\n\n");
    }

    /** @return list<string> */
    private function lines(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $value) ?: [])));
    }
}
