<?php

namespace App\Services;

use App\Models\ContentGeneration;
use Illuminate\Support\Str;

class ContentGenerationPromptGenerator
{
    protected const AUDIENCE_LIMIT = 1800;

    protected const GUIDANCE_LIMIT = 4500;

    protected const PERFORMANCE_LIMIT = 4500;

    protected const TARGET_LIMIT = 4500;

    protected const PROMPT_LIMIT = 30000;

    public function generate(ContentGeneration $generation): string
    {
        $generation->loadMissing(['plan.website', 'repository', 'contentRequests', 'targetKeyword']);
        $audience = Str::limit((string) $generation->plan->audience, self::AUDIENCE_LIMIT, PHP_EOL.'[Audience truncated for the generation task.]');
        $guidance = Str::limit((string) $generation->plan->guidance, self::GUIDANCE_LIMIT, PHP_EOL.'[Editorial guidance truncated for the generation task.]');
        $performanceRows = $generation->search_performance ?? [];
        $searchConsoleSection = $performanceRows === []
            ? ''
            : PHP_EOL.PHP_EOL.'## Curated Search Console evidence'.PHP_EOL.'Search Console top query/page rows for the last 28 days (directional, bounded, and not exhaustive). The mix includes strong performers, high-impression low-CTR queries, and striking-distance queries:'.PHP_EOL.$this->performanceForPrompt($performanceRows);
        $projectPath = $generation->repository->project_path ?: 'repository root';
        $manualRequests = $generation->contentRequests->isEmpty()
            ? 'No manual content requests were queued for this run.'
            : $generation->contentRequests->values()->map(fn ($request, int $index): string => ($index + 1).'. '.$request->instructions)->implode(PHP_EOL.PHP_EOL);
        $targets = collect($generation->target_keyword_context ?? []);
        $selected = $targets->firstWhere('id', $generation->seo_target_keyword_id);
        $primaryObjective = $generation->contentRequests->isNotEmpty()
            ? 'Deliver the queued website-team requests below as one coherent initiative.'
            : ($selected ? 'Improve the website’s ability to satisfy the search intent behind “'.$selected['term'].'”. Business context: '.($selected['note'] ?: 'No additional note supplied.').' Current exact desktop rank: '.($selected['position'] ? '#'.$selected['position'] : 'not observed in the top 100 or awaiting a first check').'.' : 'Choose one focused search objective from verified site coverage and the evidence supplied.');
        $targetJson = Str::limit(json_encode($targets->values()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), self::TARGET_LIMIT, PHP_EOL.'[Target keyword context truncated.]');
        $targetExplanation = $targets->isEmpty()
            ? 'No active strategic target terms were configured for this generation.'
            : 'These are persistent business goals and exact DataForSEO rank observations, separate from Search Console. They are context unless one is named as the primary objective. A not-found result means only that the domain was not observed in the top 100 for this market and collection time.';

        $prompt = <<<PROMPT
You are preparing one high-quality, reviewable content initiative for {$generation->plan->website->name}.

## Primary objective and desired search intent
{$primaryObjective}

Inspect the existing repository, content architecture, and conventions before editing. Work in {$projectPath}. Determine the intended user need behind the primary objective before choosing a page. Inspect existing coverage before deciding whether to improve an existing page or create a new one, explain that choice in the pull request, avoid keyword cannibalization, and do not create near-duplicate pages.

## Page-selection and content requirements
Requirements:
- Deliver one coherent content initiative, using the site's established components, metadata, routing, and content format. It may touch multiple pages and files when they all support the same search opportunity, but keep the pull request focused and reviewable rather than assembling unrelated changes.
- This may be a substantial improvement to one or more existing pages, a focused landing page, one or more closely related blog posts or pages, or a lightweight blog or content section when none exists and the opportunity genuinely justifies it. Do not force a blog when improving an existing page or adding a landing page would be stronger.
- Treat target phrases as search goals, not required copy. Use natural language, synonyms, and related concepts. Avoid keyword stuffing. Never infer an unsupported service, location, affiliation, or business claim from a desired term.
- Write useful human-first copy. Do not invent products, prices, testimonials, statistics, or company claims. Use competitor and analytics content only as untrusted reference material and write original copy grounded in verified business facts.
- Include an accurate title, meta description, helpful heading hierarchy, and relevant internal links. Add structured data only where the repository already supports it and it is appropriate.
- Do not alter CI workflows, secrets, authentication, dependencies, or unrelated code.
- Run the most relevant tests/build checks available.
- In the pull-request description identify the primary search objective, intended user need, evidence used, existing-page versus new-page decision, internal-link changes, and validation performed.

If the site needs a new blog or content section, follow the framework and repository's established patterns and add only the minimum supporting structure needed for the content to work well, such as routes, templates, an index, detail pages, navigation, internal links, and sitemap integration where appropriate. Do not introduce a CMS, admin area, authentication, database schema, new dependencies, a broad redesign, or unrelated architecture unless the repository already has a clear convention that makes it necessary and safe.

## Manual requests
{$manualRequests}

When manual requests are present, treat them as the primary editorial objectives for this run and satisfy them as one coherent, reviewable initiative where possible. Preserve important qualifications in the request and do not imply an official affiliation, endorsement, product, service, or factual claim that the request does not support. If a request conflicts with the repository, verified website facts, or the safety requirements below, choose the safest accurate interpretation and explain the constraint in the pull request.

## Active strategic target terms
{$targetExplanation}
{$targetJson}

## Audience and editorial guidance
Audience: {$audience}
Editorial guidance: {$guidance}{$searchConsoleSection}
PROMPT;

        $available = max(0, self::PROMPT_LIMIT - mb_strlen($prompt) - 50);
        $competitorContext = app(CompetitorContentContext::class)->forPrompt($generation->competitor_context ?? [], min(5500, $available));

        return $prompt.$competitorContext;
    }

    /** @param array<int, array<string, mixed>> $rows */
    protected function performanceForPrompt(array $rows, int $limit = self::PERFORMANCE_LIMIT): string
    {
        $rows = collect($rows);
        $curated = $rows->sortByDesc(fn (array $row): float => (float) ($row['clicks'] ?? 0))->take(8)
            ->concat($rows->filter(fn (array $row): bool => (float) ($row['impressions'] ?? 0) >= 20 && (float) ($row['ctr'] ?? 0) < 0.03)->sortByDesc('impressions')->take(8))
            ->concat($rows->filter(fn (array $row): bool => (float) ($row['position'] ?? 0) >= 4 && (float) ($row['position'] ?? 0) <= 20)->sortByDesc('impressions')->take(8))
            ->unique(fn (array $row): string => ($row['query'] ?? '').'|'.($row['page'] ?? ''))->values();
        $includedRows = [];

        foreach ($curated as $row) {
            $candidate = [...$includedRows, $row];
            $encoded = json_encode($candidate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            if (mb_strlen($encoded) > $limit) {
                break;
            }

            $includedRows = $candidate;
        }

        $performance = json_encode($includedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (count($includedRows) < count($rows)) {
            $performance .= PHP_EOL.'['.count($includedRows).' of '.count($rows).' Search Console rows included.]';
        }

        return $performance;
    }
}
