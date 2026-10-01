<?php

namespace App\Services;

use App\Ai\Agents\GoogleAdsCopyWriter;
use App\Models\Website;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class GoogleAdsSuggestionGenerator
{
    public function __construct(private GoogleAdsCopyWriter $writer, private GoogleAdsOpportunityFinder $opportunities) {}

    /** @return array{keywords_text: string, headlines: list<string>, descriptions: list<string>} */
    public function generate(Website $website, string $finalUrl, string $city, ?string $brief): array
    {
        $report = $website->healthReports()->where('status', 'completed')->latest('completed_at')->first();
        $page = $report?->pages()->whereIn('url', [rtrim($finalUrl, '/'), rtrim($finalUrl, '/').'/'])->first();
        $targetKeywords = $website->seoTargetKeywords()->whereNull('archived_at')->limit(10)->pluck('term')->all();
        $pageFacts = array_filter([
            'title' => $page?->title ? Str::limit($page->title, 200, '') : null,
            'description' => $page?->meta_description ? Str::limit($page->meta_description, 400, '') : null,
            'campaign_brief' => $brief,
            'target_keywords' => array_map(fn (string $term): string => Str::limit($term, 80, ''), $targetKeywords),
        ]);

        if ($pageFacts === []) {
            throw new InvalidArgumentException('Add a short description of what this landing page offers, then try again.');
        }

        $context = [
            'business_name' => Str::limit($website->name, 120, ''),
            'verified_landing_page' => $finalUrl,
            'target_city' => $city,
            'landing_page_and_business_facts' => $pageFacts,
            'search_console_research_candidates' => $this->opportunities->searchGaps($website)
                ->map(fn ($metric): string => Str::limit($metric->query, 80, ''))->all(),
        ];

        $response = $this->writer->prompt('Suggest keywords and ad copy for this single landing page. Only use relevant research candidates after checking them against the page facts. Context JSON: '.json_encode($context, JSON_THROW_ON_ERROR));
        $suggestions = [
            'keywords' => collect($response['keywords'] ?? [])->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)->all(),
            'headlines' => collect($response['headlines'] ?? [])->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)->all(),
            'descriptions' => collect($response['descriptions'] ?? [])->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)->all(),
        ];

        $validator = Validator::make($suggestions, [
            'keywords' => ['required', 'array', 'between:5,10'],
            'keywords.*' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'headlines' => ['required', 'array', 'size:3'],
            'headlines.*' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
            'descriptions' => ['required', 'array', 'size:2'],
            'descriptions.*' => ['required', 'string', 'max:90', 'distinct:ignore_case'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('The suggested ad copy did not fit Google Ads limits. Please try again.');
        }

        if (collect([...$suggestions['headlines'], ...$suggestions['descriptions']])
            ->contains(fn (string $line): bool => preg_match('/[£€$]|\b(?:GBP|VAT)\b/i', $line) === 1)) {
            throw new RuntimeException('The suggested ad copy included a price or VAT reference. Please try again.');
        }

        return [
            'keywords_text' => implode("\n", $suggestions['keywords']),
            'headlines' => array_values($suggestions['headlines']),
            'descriptions' => array_values($suggestions['descriptions']),
        ];
    }
}
