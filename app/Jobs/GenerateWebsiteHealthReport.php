<?php

namespace App\Jobs;

use App\Mail\WebsiteHealthReportReady;
use App\Models\ContentGeneration;
use App\Models\WebsiteHealthReport;
use App\Services\CopilotAgentClient;
use App\Services\GithubAppClient;
use App\Services\PageSpeedInsightsClient;
use App\Services\SearchConsoleClient;
use App\Services\WebsiteHealthAuditor;
use App\Services\WebsiteMailRecipients;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class GenerateWebsiteHealthReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public WebsiteHealthReport $report) {}

    public function uniqueId(): string
    {
        return (string) $this->report->website_id;
    }

    public function handle(WebsiteHealthAuditor $auditor, SearchConsoleClient $searchConsole, GithubAppClient $github, CopilotAgentClient $copilot, PageSpeedInsightsClient $pageSpeed, WebsiteMailRecipients $recipients): void
    {
        $this->report->update([
            'status' => WebsiteHealthReport::STATUS_RUNNING,
            'started_at' => now(),
            'error' => null,
        ]);

        try {
            $result = $auditor->audit($this->report->website);
            $pages = $result['pages'];
            unset($result['pages']);
            $pageSpeedResult = $pageSpeed->audit(collect($pages)
                ->whereBetween('status_code', [200, 299])
                ->where('is_indexable', true)
                ->sortBy('depth')
                ->pluck('url')
                ->all());
            $result = $this->includeChecks($result, $pageSpeedResult['checks']);
            $result['metrics']['pagespeed'] = $pageSpeedResult['pages'];
            $result['metrics']['changes'] = $this->changesSincePreviousReport($result['checks']);
            $result['metrics']['search_console'] = $this->searchConsoleReport($searchConsole);
            $result['metrics']['content_updates'] = $this->contentUpdates($github, $copilot);

            $this->report->pages()->delete();
            $this->report->pages()->createMany($pages);

            $this->report->update([
                ...$result,
                'status' => WebsiteHealthReport::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $this->sendReport($recipients);
        } catch (Throwable $exception) {
            $this->report->update([
                'status' => WebsiteHealthReport::STATUS_FAILED,
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<int, array<string, mixed>>  $checks
     * @return array<string, mixed>
     */
    protected function includeChecks(array $result, array $checks): array
    {
        foreach ($checks as $check) {
            $result['checks'][] = $check;
            $result[$check['status'].'_checks']++;
            $result['categories'][$check['category']] ??= ['passed' => 0, 'warning' => 0, 'failed' => 0];
            $result['categories'][$check['category']][$check['status']]++;
        }

        $result['overall_status'] = $result['failed_checks'] > 0 ? 'critical' : ($result['warning_checks'] > 0 ? 'needs_attention' : 'healthy');

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    protected function contentUpdates(GithubAppClient $github, CopilotAgentClient $copilot): array
    {
        $plan = $this->report->website->contentPlan()->where('enabled', true)->first();

        if (! $plan) {
            return [];
        }

        return $plan->generations()
            ->with(['repository.installation', 'requester.githubAuthorization'])
            ->whereNotNull('pull_request_number')
            ->where(function ($query): void {
                $query->where('status', ContentGeneration::STATUS_PULL_REQUEST_OPEN)
                    ->orWhere('merged_at', '>=', $this->contentUpdatesStartDate());
            })
            ->get()
            ->map(function ($generation) use ($github, $copilot): ?array {
                try {
                    $details = $this->contentUpdateDetails($generation, $github, $copilot);
                    $pullRequest = $details['pull_request'];
                    $mergedAt = filled($pullRequest['merged_at'] ?? null)
                        ? Carbon::parse($pullRequest['merged_at'])
                        : $generation->merged_at;

                    if ($mergedAt && ($generation->status !== ContentGeneration::STATUS_COMPLETED || ! $generation->merged_at)) {
                        $generation->update([
                            'status' => ContentGeneration::STATUS_COMPLETED,
                            'pull_request_state' => 'closed',
                            'completed_at' => $mergedAt,
                            'merged_at' => $mergedAt,
                        ]);
                    }

                    if (! $mergedAt || $mergedAt->isBefore($this->contentUpdatesStartDate())) {
                        return null;
                    }

                    return [
                        'title' => (string) ($pullRequest['title'] ?? "Content update #{$generation->pull_request_number}"),
                        'summary' => Str::limit(trim((string) ($pullRequest['body'] ?? '')), 600),
                        'url' => (string) ($pullRequest['html_url'] ?? $generation->pull_request_url),
                        'merged_at' => $mergedAt->toIso8601String(),
                        'additions' => (int) ($pullRequest['additions'] ?? 0),
                        'deletions' => (int) ($pullRequest['deletions'] ?? 0),
                        'changed_files' => (int) ($pullRequest['changed_files'] ?? count($details['files'])),
                        'files' => collect($details['files'])->take(10)->map(fn (array $file): array => [
                            'name' => (string) ($file['filename'] ?? ''),
                            'status' => (string) ($file['status'] ?? 'modified'),
                            'additions' => (int) ($file['additions'] ?? 0),
                            'deletions' => (int) ($file['deletions'] ?? 0),
                        ])->all(),
                    ];
                } catch (Throwable) {
                    if (! $generation->merged_at || $generation->merged_at->isBefore($this->contentUpdatesStartDate())) {
                        return null;
                    }

                    return [
                        'title' => "Content update #{$generation->pull_request_number}",
                        'summary' => '',
                        'url' => $generation->pull_request_url,
                        'merged_at' => $generation->merged_at?->toIso8601String(),
                        'additions' => 0,
                        'deletions' => 0,
                        'changed_files' => 0,
                        'files' => [],
                    ];
                }
            })
            ->filter()
            ->sortBy('merged_at')
            ->values()
            ->all();
    }

    /** @return array{pull_request: array<string, mixed>, files: array<int, array<string, mixed>>} */
    protected function contentUpdateDetails(ContentGeneration $generation, GithubAppClient $github, CopilotAgentClient $copilot): array
    {
        try {
            return $github->pullRequestDetails($generation->repository, $generation->pull_request_number);
        } catch (Throwable $exception) {
            $authorization = $generation->requester?->githubAuthorization;

            if (! $authorization || ! $generation->copilot_task_id) {
                throw $exception;
            }

            $task = $copilot->task($authorization, $generation->repository, $generation->copilot_task_id);
            $headRef = data_get($task, 'sessions.0.head_ref');

            if (! is_string($headRef) || $headRef === '') {
                throw $exception;
            }

            $pullRequest = $github->pullRequestForHead($generation->repository, $headRef);
            $pullRequestNumber = (int) ($pullRequest['number'] ?? 0);

            if ($pullRequestNumber < 1) {
                throw $exception;
            }

            $generation->update([
                'pull_request_number' => $pullRequestNumber,
                'pull_request_url' => (string) ($pullRequest['html_url'] ?? "https://github.com/{$generation->repository->full_name}/pull/{$pullRequestNumber}"),
            ]);

            return $github->pullRequestDetails($generation->repository, $pullRequestNumber);
        }
    }

    protected function contentUpdatesStartDate(): Carbon
    {
        return $this->report->created_at->copy()->subDays((int) config('forms.health_reports.frequency_days'));
    }

    /** @return array<string, mixed>|null */
    protected function searchConsoleReport(SearchConsoleClient $searchConsole): ?array
    {
        $connection = $this->report->website->searchConsoleConnection;

        if (! $connection?->property_url) {
            return null;
        }

        try {
            return $searchConsole->report($connection);
        } catch (Throwable) {
            return null;
        }
    }

    protected function sendReport(WebsiteMailRecipients $recipients): void
    {
        $website = $this->report->website()->with(['owner', 'members'])->firstOrFail();
        $reportRecipients = $recipients->forReports($website);

        if ($reportRecipients === []) {
            return;
        }

        foreach ($reportRecipients as $recipient) {
            Mail::to($recipient)->send(new WebsiteHealthReportReady(
                $this->report->fresh(['website', 'pages']),
                showGithubLinks: ! $recipients->isViewer($website, $recipient),
            ));
        }

        $this->report->update(['emailed_at' => now()]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $checks
     * @return array{new_issues: int, resolved_issues: int}
     */
    protected function changesSincePreviousReport(array $checks): array
    {
        $previousReport = $this->report->website->healthReports()
            ->whereKeyNot($this->report->id)
            ->where('status', WebsiteHealthReport::STATUS_COMPLETED)
            ->latest('completed_at')
            ->first();

        $currentIssues = collect($checks)
            ->reject(fn (array $check) => $check['status'] === 'passed')
            ->mapWithKeys(fn (array $check) => [$check['category'].':'.$check['key'] => $check['status']]);
        $previousIssues = collect($previousReport?->checks ?? [])
            ->reject(fn (array $check) => $check['status'] === 'passed')
            ->mapWithKeys(fn (array $check) => [$check['category'].':'.$check['key'] => $check['status']]);

        return [
            'new_issues' => $currentIssues->keys()->diff($previousIssues->keys())->count(),
            'resolved_issues' => $previousIssues->keys()->diff($currentIssues->keys())->count(),
        ];
    }
}
