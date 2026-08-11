<?php

namespace App\Services;

use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WebsiteHealthReportPromptGenerator
{
    public function generate(WebsiteHealthReport $report): string
    {
        $reportIssues = collect($report->checks)->whereIn('status', ['warning', 'failed']);
        $pageIssues = $report->pages
            ->map(fn ($page) => [
                'url' => $page->url,
                'page' => $page,
                'issues' => collect($page->checks)->whereIn('status', ['warning', 'failed']),
            ])
            ->filter(fn (array $page) => $page['issues']->isNotEmpty());

        return collect([
            'You are an expert web developer, technical SEO specialist, accessibility consultant, and security engineer.',
            '',
            "Create a prioritised implementation plan to resolve every warning and error in this health report for {$report->website->name}.",
            'Website: '.($report->website->primaryDomain()?->domain ?? 'Unknown domain'),
            'Report date: '.$report->created_at->toDateString(),
            '',
            'Requirements:',
            '- Address failed checks first, followed by warnings.',
            '- Group related fixes so they can be implemented efficiently.',
            '- Give precise, actionable changes and include code examples where useful.',
            '- Preserve the website’s existing design, content intent, analytics, forms, and working functionality.',
            '- Do not invent access to systems or source code that have not been provided.',
            '- After making changes, explain how to verify every fix.',
            '',
            'Site-wide findings:',
            $this->formatIssues($reportIssues),
            '',
            'Page-specific findings:',
            $pageIssues->isEmpty() ? 'No page-specific warnings or errors were recorded.' : $pageIssues
                ->map(fn (array $page) => $page['url'].PHP_EOL.$this->formatIssues($page['issues'], '  ', $page['page']))
                ->implode(PHP_EOL.PHP_EOL),
            '',
            'Return the work as:',
            '1. An executive summary of the most important problems.',
            '2. A prioritised checklist split into critical, high, medium, and low priority.',
            '3. Page-by-page implementation instructions.',
            '4. A verification checklist mapping each reported finding to its expected result.',
        ])->implode(PHP_EOL);
    }

    /** @param Collection<int, array<string, mixed>> $issues */
    protected function formatIssues(Collection $issues, string $indent = '', ?WebsiteHealthReportPage $page = null): string
    {
        if ($issues->isEmpty()) {
            return $indent.'No warnings or errors were recorded.';
        }

        return $issues->map(fn (array $issue) => sprintf(
            '%s- [%s] %s: %s',
            $indent,
            strtoupper($issue['status']),
            $issue['label'],
            $this->message($issue, $page),
        ))->implode(PHP_EOL);
    }

    /** @param array<string, mixed> $issue */
    protected function message(array $issue, ?WebsiteHealthReportPage $page): string
    {
        if ($page && $issue['status'] === 'warning' && $issue['key'] === 'page_title' && Str::length((string) $page->title) > 65) {
            return 'The title is '.Str::length($page->title).' characters long. Aim for 65 or fewer so it is less likely to be truncated in search results. Current title: '.$page->title;
        }

        if ($page && $issue['status'] === 'warning' && $issue['key'] === 'meta_description' && Str::length((string) $page->meta_description) > 170) {
            return 'The meta description is '.Str::length($page->meta_description).' characters long. Aim for 170 or fewer so it is less likely to be truncated in search results.';
        }

        return $issue['message'];
    }
}
