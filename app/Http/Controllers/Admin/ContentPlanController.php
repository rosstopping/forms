<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateContentPlanRequest;
use App\Jobs\StartContentGeneration;
use App\Models\Website;
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
            $this->ensureReady($request, $website);
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
}
