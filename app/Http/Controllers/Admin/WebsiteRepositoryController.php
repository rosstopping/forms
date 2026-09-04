<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteRepositoryRequest;
use App\Models\GithubInstallation;
use App\Models\Website;
use App\Services\GithubAppClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WebsiteRepositoryController extends Controller
{
    public function __construct(protected GithubAppClient $github) {}

    public function create(Request $request, Website $website): View|RedirectResponse
    {
        $this->authorizeWebsite($request, $website);

        $installations = GithubInstallation::query()
            ->where('status', GithubInstallation::STATUS_ACTIVE)
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('installed_by', $request->user()->id))
            ->latest()
            ->get();

        if ($installations->isEmpty()) {
            return Redirect::route('admin.github.connect', $website);
        }

        $unavailableInstallations = collect();
        $repositories = $installations->flatMap(function (GithubInstallation $installation) use ($unavailableInstallations): Collection {
            try {
                $repositories = $this->github->repositories($installation->installation_id);
            } catch (RequestException $exception) {
                if (! $this->markUnavailableWhenNotFound($installation, $exception)) {
                    throw $exception;
                }

                $unavailableInstallations->push($installation->account_login);

                return collect();
            }

            return collect($repositories)->map(fn (array $repository) => [
                ...$repository,
                'github_installation_id' => $installation->id,
                'account_login' => $installation->account_login,
            ]);
        })->sortBy('full_name')->values();

        return view('admin.website-repositories.create', compact('website', 'repositories', 'unavailableInstallations'));
    }

    public function store(StoreWebsiteRepositoryRequest $request, Website $website): RedirectResponse
    {
        $data = $request->validated();
        $installation = GithubInstallation::query()
            ->where('status', GithubInstallation::STATUS_ACTIVE)
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('installed_by', $request->user()->id))
            ->findOrFail($data['github_installation_id']);
        try {
            $repository = collect($this->github->repositories($installation->installation_id))
                ->firstWhere('id', (int) $data['repository_id']);
        } catch (RequestException $exception) {
            if (! $this->markUnavailableWhenNotFound($installation, $exception)) {
                throw $exception;
            }

            return Redirect::route('admin.websites.show', $website)
                ->with('error', "The GitHub installation for {$installation->account_login} is no longer available. Reconnect the Sitewell GitHub App and try again.");
        }

        if (! is_array($repository)) {
            throw ValidationException::withMessages([
                'repository_id' => 'The selected repository is not available to this GitHub installation.',
            ]);
        }

        $website->repository()->updateOrCreate([], [
            'github_installation_id' => $installation->id,
            'repository_id' => $repository['id'],
            'full_name' => $repository['full_name'],
            'default_branch' => $repository['default_branch'],
            'private' => $repository['private'],
            'permissions' => $repository['permissions'] ?? [],
            'project_path' => $data['project_path'] ?? null,
        ]);

        return Redirect::route('admin.websites.show', $website)
            ->with('status', "Repository {$repository['full_name']} connected.");
    }

    public function destroy(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_if($website->repository?->remediationRuns()->exists(), 422, 'The repository has remediation history and cannot be disconnected.');

        $website->repository?->delete();

        return Redirect::route('admin.websites.show', $website)
            ->with('status', 'The repository was disconnected.');
    }

    protected function authorizeWebsite(Request $request, Website $website): void
    {
        abort_unless($website->isManageableBy($request->user()), 403);
    }

    protected function markUnavailableWhenNotFound(GithubInstallation $installation, RequestException $exception): bool
    {
        if ($exception->response->status() !== 404) {
            return false;
        }

        $installation->update(['status' => GithubInstallation::STATUS_DELETED]);

        return true;
    }
}
