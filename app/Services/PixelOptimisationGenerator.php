<?php

namespace App\Services;

use App\Ai\Agents\PixelOptimisationWriter;
use App\Enums\OptimisationStatus;
use App\Enums\OptimisationType;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReportPage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PixelOptimisationGenerator
{
    public function __construct(
        private PixelOptimisationWriter $writer,
        private OptimisationDeploymentManager $optimisations,
        private OptimisationValueSanitizer $values,
    ) {}

    public function generate(Website $website, WebsiteHealthReportPage $page, User $author): int
    {
        $response = $this->writer->prompt($this->prompt($website, $page));
        $created = 0;

        foreach (Arr::wrap($response['changes'] ?? []) as $change) {
            if (! is_array($change)) {
                continue;
            }

            $type = OptimisationType::tryFrom((string) ($change['type'] ?? ''));
            $value = trim((string) ($change['value'] ?? ''));

            if (! in_array($type, [OptimisationType::Title, OptimisationType::MetaDescription], true)
                || $value === ''
                || ($type === OptimisationType::Title && Str::length($value) > 65)
                || ($type === OptimisationType::MetaDescription && Str::length($value) > 170)
                || $this->alreadyExists($page, $type)) {
                continue;
            }

            $sanitized = $this->values->sanitize($type, $value, pageUrl: $page->url);
            $originalValue = $type === OptimisationType::Title ? $page->title : $page->meta_description;

            if ($sanitized === trim((string) $originalValue)) {
                continue;
            }

            $this->optimisations->create($website, $page, [
                'type' => $type->value,
                'target_description' => Str::limit(trim((string) ($change['reason'] ?? 'AI-generated fix')), 255, ''),
                'original_value' => $originalValue,
                'new_value' => $sanitized,
            ], $author);
            $created++;
        }

        return $created;
    }

    private function alreadyExists(WebsiteHealthReportPage $page, OptimisationType $type): bool
    {
        return $page->optimisations()
            ->where('type', $type)
            ->whereIn('status', [OptimisationStatus::Draft, OptimisationStatus::Approved, OptimisationStatus::Deployed])
            ->exists();
    }

    private function prompt(Website $website, WebsiteHealthReportPage $page): string
    {
        $findings = collect($page->checks)
            ->whereNotIn('status', ['passed'])
            ->map(fn (array $check): string => ($check['label'] ?? 'Finding').': '.($check['message'] ?? ''))
            ->implode("\n");

        return <<<PROMPT
Website: {$website->name}
Page URL: {$page->url}
Current title: {$page->title}
Current meta description: {$page->meta_description}
Approximate word count: {$page->word_count}

Crawl findings:
{$findings}

Suggest only safe title or meta-description changes supported by these facts. An empty changes array is correct when evidence is insufficient.
PROMPT;
    }
}
