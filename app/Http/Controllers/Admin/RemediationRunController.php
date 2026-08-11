<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRemediationRunRequest;
use App\Jobs\StartCopilotRemediation;
use App\Models\RemediationRun;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class RemediationRunController extends Controller
{
    public function store(StoreRemediationRunRequest $request, Website $website, WebsiteHealthReport $websiteHealthReport): RedirectResponse
    {
        abort_unless($websiteHealthReport->website_id === $website->id, 404);
        abort_unless($websiteHealthReport->status === WebsiteHealthReport::STATUS_COMPLETED, 422);

        $repository = $website->repository;

        if (! $repository) {
            throw ValidationException::withMessages(['repository' => 'Connect a GitHub repository before preparing a remediation.']);
        }

        if (! $request->user()->githubAuthorization()->exists()) {
            throw ValidationException::withMessages(['github' => 'Authorize GitHub before starting a Copilot remediation.']);
        }

        $availableFindings = $this->availableFindings($websiteHealthReport);
        $selected = collect($request->validated('findings'));

        if ($selected->diff($availableFindings->keys())->isNotEmpty()) {
            throw ValidationException::withMessages(['findings' => 'One or more selected findings are no longer available.']);
        }

        $run = RemediationRun::query()->firstOrCreate(
            [
                'website_health_report_id' => $websiteHealthReport->id,
                'website_repository_id' => $repository->id,
            ],
            [
                'requested_by' => $request->user()->id,
                'status' => RemediationRun::STATUS_AWAITING_RUNNER,
                'findings' => $selected->map(fn (string $key) => $availableFindings[$key])->values()->all(),
            ],
        );

        if ($run->wasRecentlyCreated) {
            StartCopilotRemediation::dispatch($run);
        }

        return Redirect::route('admin.website-health-reports.show', [$website, $websiteHealthReport])
            ->with('status', $run->wasRecentlyCreated
                ? 'The GitHub Copilot remediation has been queued.'
                : 'A remediation request already exists for this report.');
    }

    /** @return Collection<string, array<string, mixed>> */
    protected function availableFindings(WebsiteHealthReport $report): Collection
    {
        $siteFindings = collect($report->checks)
            ->whereIn('status', ['warning', 'failed'])
            ->mapWithKeys(fn (array $finding) => [
                'site:'.$finding['category'].':'.$finding['key'] => [
                    'scope' => 'site',
                    'key' => $finding['key'],
                    'category' => $finding['category'],
                    'label' => $finding['label'],
                    'status' => $finding['status'],
                    'message' => $finding['message'],
                ],
            ]);

        $pageFindings = $report->pages->flatMap(fn ($page) => collect($page->checks)
            ->whereIn('status', ['warning', 'failed'])
            ->mapWithKeys(fn (array $finding) => [
                'page:'.$page->id.':'.$finding['key'] => [
                    'scope' => 'page',
                    'key' => $finding['key'],
                    'url' => $page->url,
                    'label' => $finding['label'],
                    'status' => $finding['status'],
                    'message' => $finding['message'],
                ],
            ]));

        return $siteFindings->merge($pageFindings);
    }
}
