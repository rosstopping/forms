<?php

namespace App\Services;

use App\Ai\Agents\ContentRequestPixelWriter;
use App\Enums\OptimisationStatus;
use App\Enums\OptimisationType;
use App\Models\ContentRequest;
use App\Models\User;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ContentRequestPixelOptimisationGenerator
{
    public function __construct(
        private ContentRequestPixelWriter $writer,
        private OptimisationDeploymentManager $optimisations,
        private OptimisationValueSanitizer $values,
        private PixelUrlNormalizer $urls,
    ) {}

    public function generate(ContentRequest $contentRequest, User $author): int
    {
        $contentRequest->loadMissing('website');
        $website = $contentRequest->website;

        if (! config('forms.pixel_ui_enabled') || ! $website->pixel_enabled) {
            return 0;
        }

        $pages = $this->candidatePages($contentRequest);

        if ($pages->isEmpty()) {
            $contentRequest->update([
                'pixel_processed_at' => now(),
                'pixel_error' => 'No Pixel-detected pages with crawl evidence are available yet.',
            ]);

            return 0;
        }

        $response = $this->writer->prompt($this->prompt($contentRequest, $pages));
        $created = 0;

        foreach (Arr::wrap($response['changes'] ?? []) as $change) {
            if (! is_array($change)) {
                continue;
            }

            $url = (string) ($change['url'] ?? '');
            $page = $pages->get($this->urls->hash($url));
            $type = OptimisationType::tryFrom((string) ($change['type'] ?? ''));
            $value = trim((string) ($change['value'] ?? ''));

            if (! $page instanceof WebsiteHealthReportPage
                || ! in_array($type, [OptimisationType::Title, OptimisationType::MetaDescription], true)
                || $value === ''
                || ($type === OptimisationType::Title && Str::length($value) > 65)
                || ($type === OptimisationType::MetaDescription && Str::length($value) > 170)
                || $this->alreadyExists($contentRequest, $page, $type)) {
                continue;
            }

            $sanitized = $this->values->sanitize($type, $value, pageUrl: $page->url);
            $originalValue = $type === OptimisationType::Title ? $page->title : $page->meta_description;

            if ($sanitized === trim((string) $originalValue)) {
                continue;
            }

            $this->optimisations->createForUrl($website, $page->url, [
                'content_request_id' => $contentRequest->id,
                'type' => $type->value,
                'target_description' => Str::limit(trim((string) ($change['reason'] ?? 'Content todo')), 255, ''),
                'original_value' => $originalValue,
                'new_value' => $sanitized,
            ], $author, $page);
            $created++;
        }

        $contentRequest->update([
            'pixel_processed_at' => now(),
            'pixel_error' => $created === 0 ? 'The todo could not be safely represented as an existing-page Pixel metadata change.' : null,
        ]);

        return $created;
    }

    /** @return Collection<string, WebsiteHealthReportPage> */
    private function candidatePages(ContentRequest $contentRequest): Collection
    {
        $website = $contentRequest->website;
        $detectedHashes = $website->pixelPages()->latest('last_seen_at')->limit(50)->pluck('url_hash');
        $report = $website->healthReports()->where('status', WebsiteHealthReport::STATUS_COMPLETED)->latest('completed_at')->first();

        if (! $report || $detectedHashes->isEmpty()) {
            return collect();
        }

        return $report->pages()->get()->filter(
            fn (WebsiteHealthReportPage $page): bool => $detectedHashes->contains($this->urls->hash($page->url)),
        )->keyBy(fn (WebsiteHealthReportPage $page): string => $this->urls->hash($page->url));
    }

    private function alreadyExists(ContentRequest $contentRequest, WebsiteHealthReportPage $page, OptimisationType $type): bool
    {
        return $contentRequest->website->optimisations()
            ->where('url_hash', $this->urls->hash($page->url))
            ->where('type', $type)
            ->whereIn('status', [OptimisationStatus::Draft, OptimisationStatus::Approved, OptimisationStatus::Deployed])
            ->exists();
    }

    /** @param Collection<string, WebsiteHealthReportPage> $pages */
    private function prompt(ContentRequest $contentRequest, Collection $pages): string
    {
        $candidates = $pages->values()->map(fn (WebsiteHealthReportPage $page): array => [
            'url' => $page->url,
            'current_title' => $page->title,
            'current_meta_description' => $page->meta_description,
        ])->all();
        $encodedCandidates = json_encode($candidates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $instructions = Str::limit($contentRequest->instructions, 3000, '');

        return <<<PROMPT
Content todo:
{$instructions}

Existing Pixel-detected pages with verified crawl metadata:
{$encodedCandidates}

Prepare only safe title or meta-description drafts that materially address the todo on an existing candidate page. An empty changes array is correct when Pixel is not a suitable delivery method.
PROMPT;
    }
}
