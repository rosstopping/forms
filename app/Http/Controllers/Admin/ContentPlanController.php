<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateContentPlanRequest;
use App\Jobs\StartContentGeneration;
use App\Models\ContentGeneration;
use App\Models\Website;
use App\Services\GithubAppClient;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class ContentPlanController extends Controller
{
    public function update(UpdateContentPlanRequest $request, Website $website): RedirectResponse
    {
        $data = $request->validated();

        if ($data['enabled']) {
            try {
                $this->ensureReady($request, $website);
            } catch (ValidationException $exception) {
                $website->contentPlan()->updateOrCreate(
                    ['website_id' => $website->id],
                    [...$data, 'enabled' => false, 'created_by' => $request->user()->id],
                );

                throw $exception;
            }
        }

        $website->contentPlan()->updateOrCreate(['website_id' => $website->id], [...$data, 'created_by' => $request->user()->id]);

        return Redirect::route('admin.websites.show', $website)->with('status', 'Weekly content plan updated.');
    }

    public function generate(Request $request, Website $website): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $this->ensureReady($request, $website);
        $plan = $website->contentPlan()->firstOrCreate([], ['created_by' => $request->user()->id]);
        $generation = $plan->generations()->firstOrCreate(
            ['scheduled_for' => now($plan->timezone)->toDateString()],
            ['website_repository_id' => $website->repository->id, 'requested_by' => $request->user()->id],
        );
        if ($generation->wasRecentlyCreated) {
            StartContentGeneration::dispatch($generation);
        }

        return Redirect::route('admin.websites.show', $website)->with('status', $generation->wasRecentlyCreated ? 'Content generation queued.' : 'A content generation already exists for today.');
    }

    public function syncGeneration(Request $request, Website $website, ContentGeneration $contentGeneration, GithubAppClient $github): RedirectResponse
    {
        $this->authorizeGeneration($request, $website, $contentGeneration);
        abort_unless($contentGeneration->pull_request_number, 422);

        $contentGeneration->loadMissing('repository.installation');
        $pullRequest = $github->pullRequestDetails($contentGeneration->repository, $contentGeneration->pull_request_number)['pull_request'];
        $state = (string) ($pullRequest['state'] ?? $contentGeneration->pull_request_state);
        $mergedAt = filled($pullRequest['merged_at'] ?? null) ? Carbon::parse($pullRequest['merged_at']) : null;

        $contentGeneration->update([
            'pull_request_state' => $state,
            'status' => $mergedAt ? ContentGeneration::STATUS_COMPLETED : $contentGeneration->status,
            'completed_at' => $mergedAt ?: $contentGeneration->completed_at,
            'merged_at' => $mergedAt ?: $contentGeneration->merged_at,
        ]);

        $message = match (true) {
            $mergedAt !== null => 'GitHub confirmed that the content pull request was merged.',
            $state === 'closed' => 'GitHub confirmed that the pull request was closed without merging. Cancel it if no further action is needed.',
            default => 'GitHub confirmed that the pull request is still open.',
        };

        return Redirect::route('admin.websites.show', $website)->with('status', $message);
    }

    public function cancelGeneration(Request $request, Website $website, ContentGeneration $contentGeneration): RedirectResponse
    {
        $this->authorizeGeneration($request, $website, $contentGeneration);
        abort_unless($contentGeneration->status === ContentGeneration::STATUS_PULL_REQUEST_OPEN, 422);

        $contentGeneration->update([
            'status' => ContentGeneration::STATUS_CANCELLED,
            'completed_at' => now(),
            'error' => 'Cancelled manually after the pull request lifecycle could not be confirmed.',
        ]);

        return Redirect::route('admin.websites.show', $website)->with('status', 'Content generation cancelled.');
    }

    protected function ensureReady(Request $request, Website $website): void
    {
        $website->loadMissing(['repository', 'searchConsoleConnection']);
        $errors = [];
        if (! $website->repository) {
            $errors['enabled'] = 'Connect a GitHub repository first.';
        }
        if (! $website->searchConsoleConnection?->property_url) {
            $errors['enabled'] = 'Connect a Search Console property first.';
        }
        if (! $request->user()?->githubAuthorization) {
            $errors['enabled'] = 'Authorize GitHub Copilot first.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function authorizeGeneration(Request $request, Website $website, ContentGeneration $contentGeneration): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($contentGeneration->plan()->where('website_id', $website->id)->exists(), 404);
    }
}
