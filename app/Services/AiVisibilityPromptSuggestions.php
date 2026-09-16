<?php

namespace App\Services;

use App\Models\AiVisibilityPrompt;
use App\Models\AiVisibilitySetting;
use App\Models\Website;
use Illuminate\Support\Str;

class AiVisibilityPromptSuggestions
{
    public function __construct(private AiVisibilitySetup $setup) {}

    /** @return array<int, array{prompt: string, topic: string, location: ?string, seo_target_keyword_id: ?int}> */
    public function forWebsite(Website $website, ?AiVisibilitySetting $settings = null): array
    {
        $settings ??= $this->setup->settings($website);
        $terms = $website->seoTargetKeywords()->whereNull('archived_at')->orderByRaw("CASE WHEN priority = 'high' THEN 0 ELSE 1 END")->orderBy('id')->limit(15)->get();
        $locations = $settings?->locations ?? [];
        $ideas = $terms->map(fn ($term) => ['topic' => $term->term, 'location' => null, 'seo_target_keyword_id' => $term->id]);
        foreach ($settings?->services ?? [] as $service) {
            foreach ($locations ?: [null] as $location) {
                $ideas->push(['topic' => $service, 'location' => $location, 'seo_target_keyword_id' => null]);
            }
        }
        $existing = AiVisibilityPrompt::withTrashed()->where('website_id', $website->id)->pluck('prompt')->map(fn ($prompt) => $this->suggestionKey($prompt));

        return $ideas->map(function (array $idea): array {
            $topic = Str::limit(Str::squish($idea['topic']), 180, '');
            $subject = $topic.($idea['location'] ? ' in '.$idea['location'] : '');

            return [...$idea, 'prompt' => 'Which businesses would you recommend for '.$subject.'?'];
        })->unique(fn ($idea) => $this->suggestionKey($idea['prompt']))->reject(fn ($idea) => $existing->contains($this->suggestionKey($idea['prompt'])))->take(15)->values()->all();
    }

    private function suggestionKey(string $prompt): string
    {
        $words = preg_split('/[^\pL\pN]+/u', Str::lower($prompt));

        return collect($words)->reject(fn ($word) => in_array($word, ['', 'which', 'businesses', 'would', 'you', 'recommend', 'for', 'in', 'the', 'a', 'an'], true))->map(fn ($word) => preg_replace('/(?<=.)s$/', '', $word))->sort()->implode(' ');
    }
}
