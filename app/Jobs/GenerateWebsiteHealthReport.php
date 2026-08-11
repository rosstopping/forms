<?php

namespace App\Jobs;

use App\Mail\WebsiteHealthReportReady;
use App\Models\User;
use App\Models\WebsiteHealthReport;
use App\Services\GithubAppClient;
use App\Services\SearchConsoleClient;
use App\Services\WebsiteHealthAuditor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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

    public function handle(WebsiteHealthAuditor $auditor, SearchConsoleClient $searchConsole, GithubAppClient $github): void
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
            $result['metrics']['changes'] = $this->changesSincePreviousReport($result['checks']);
            $result['metrics']['search_console'] = $this->searchConsoleReport($searchConsole);
            $result['metrics']['content_updates'] = $this->contentUpdates($github);

            $this->report->pages()->delete();
            $this->report->pages()->createMany($pages);

            $this->report->update([
                ...$result,
                'status' => WebsiteHealthReport::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $this->sendReport();
        } catch (Throwable $exception) {
            $this->report->update([
                'status' => WebsiteHealthReport::STATUS_FAILED,
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function contentUpdates(GithubAppClient $github): array
    {
        $plan = $this->report->website->contentPlan()->where('enabled', true)->first();

        if (! $plan) {
            return [];
        }

        return $plan->generations()
            ->with('repository.installation')
            ->whereNotNull('pull_request_number')
            ->whereNotNull('merged_at')
            ->where('merged_at', '>=', $this->report->created_at->copy()->subDays((int) config('forms.health_reports.frequency_days')))
            ->oldest('merged_at')
            ->get()
            ->map(function ($generation) use ($github): array {
                try {
                    $details = $github->pullRequestDetails($generation->repository, $generation->pull_request_number);
                    $pullRequest = $details['pull_request'];

                    return [
                        'title' => (string) ($pullRequest['title'] ?? "Content update #{$generation->pull_request_number}"),
                        'summary' => Str::limit(trim((string) ($pullRequest['body'] ?? '')), 600),
                        'url' => (string) ($pullRequest['html_url'] ?? $generation->pull_request_url),
                        'merged_at' => (string) ($pullRequest['merged_at'] ?? $generation->merged_at?->toIso8601String()),
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
            ->all();
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

    protected function sendReport(): void
    {
        $website = $this->report->website()->with('owner')->firstOrFail();
        $recipients = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->pluck('email')
            ->push($website->owner?->email)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($recipients === []) {
            return;
        }

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new WebsiteHealthReportReady($this->report->fresh(['website', 'pages'])));
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
