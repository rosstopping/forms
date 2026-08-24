<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePagePixelOptimisations;
use App\Jobs\StartCopilotRemediation;
use App\Models\RemediationRun;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class ReportRemediationController extends Controller
{
    public function __invoke(Request $request, Website $website, WebsiteHealthReport $websiteHealthReport): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() && $website->isManageableBy($request->user()), 403);
        abort_unless($websiteHealthReport->website_id === $website->id, 404);
        abort_unless($websiteHealthReport->status === WebsiteHealthReport::STATUS_COMPLETED, 422);

        $websiteHealthReport->loadMissing('pages');
        $messages = collect();
        $pixelPages = collect();

        if (config('forms.pixel_ui_enabled') && $website->pixel_enabled) {
            $pixelPages = $websiteHealthReport->pages->filter(fn ($page): bool => collect($page->checks)
                ->whereIn('status', ['warning', 'failed'])
                ->whereIn('key', ['page_title', 'meta_description'])
                ->isNotEmpty());

            foreach ($pixelPages as $page) {
                GeneratePagePixelOptimisations::dispatch($page, $request->user());
            }

            if ($pixelPages->isNotEmpty()) {
                $messages->push('Pixel drafts queued for '.$pixelPages->count().' '.($pixelPages->count() === 1 ? 'page' : 'pages').'.');
            }
        }

        $findings = $this->findings($websiteHealthReport);
        $repository = $website->repository;
        $githubAvailable = $repository && $request->user()->githubAuthorization()->exists() && $findings->isNotEmpty();

        if ($githubAvailable) {
            $run = RemediationRun::query()->firstOrCreate(
                [
                    'website_health_report_id' => $websiteHealthReport->id,
                    'website_repository_id' => $repository->id,
                ],
                [
                    'requested_by' => $request->user()->id,
                    'status' => RemediationRun::STATUS_AWAITING_RUNNER,
                    'findings' => $findings->values()->all(),
                ],
            );

            if ($run->wasRecentlyCreated) {
                StartCopilotRemediation::dispatch($run);
                $messages->push('GitHub remediation queued for review.');
            } else {
                $messages->push('The GitHub remediation for this report already exists.');
            }
        }

        if ($messages->isEmpty()) {
            throw ValidationException::withMessages([
                'remediation' => 'Enable Pixel for supported page fixes or connect and authorize GitHub for repository remediation.',
            ]);
        }

        return Redirect::route('admin.website-health-reports.show', [$website, $websiteHealthReport])
            ->with('status', $messages->implode(' '));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function findings(WebsiteHealthReport $report): Collection
    {
        $siteFindings = collect($report->checks)
            ->whereIn('status', ['warning', 'failed'])
            ->map(fn (array $finding): array => [
                'scope' => 'site',
                'key' => $finding['key'],
                'category' => $finding['category'],
                'label' => $finding['label'],
                'status' => $finding['status'],
                'message' => $finding['message'],
            ]);

        $pageFindings = $report->pages->flatMap(fn ($page) => collect($page->checks)
            ->whereIn('status', ['warning', 'failed'])
            ->map(fn (array $finding): array => [
                'scope' => 'page',
                'key' => $finding['key'],
                'url' => $page->url,
                'label' => $finding['label'],
                'status' => $finding['status'],
                'message' => $finding['message'],
            ]));

        return $siteFindings->merge($pageFindings)->values();
    }
}
