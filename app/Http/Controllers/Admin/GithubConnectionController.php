<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GithubInstallation;
use App\Models\Website;
use App\Services\GithubAppClient;
use App\Services\GithubOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use Throwable;

class GithubConnectionController extends Controller
{
    public function __construct(
        protected GithubAppClient $github,
        protected GithubOAuthClient $oauth,
    ) {}

    public function create(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);

        $slug = (string) config('services.github.app_slug');

        if ($slug === '') {
            return Redirect::route('admin.websites.show', $website)
                ->with('error', 'The GitHub App has not been configured.');
        }

        $state = Crypt::encryptString(json_encode([
            'website_id' => $website->id,
            'user_id' => $request->user()->id,
        ], JSON_THROW_ON_ERROR));

        return Redirect::away("https://github.com/apps/{$slug}/installations/new?".http_build_query(['state' => $state]));
    }

    public function authorizeBuilder(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $state = Crypt::encryptString(json_encode([
            'user_id' => $request->user()->id,
            'return_to' => 'website-builder',
        ], JSON_THROW_ON_ERROR));

        return Redirect::away($this->oauth->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('code')) {
            return $this->completeAuthorization($request);
        }

        $data = $request->validate([
            'installation_id' => ['required', 'integer'],
            'state' => ['required', 'string'],
        ]);

        try {
            $state = json_decode(Crypt::decryptString($data['state']), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(403, 'The GitHub connection request is invalid.');
        }

        abort_unless(data_get($state, 'user_id') === $request->user()->id, 403);

        if (data_get($state, 'return_to') === 'website-builder') {
            $authorization = $this->oauth->authorize($request->user(), $data['code']);

            return Redirect::route('admin.website-builder.create')
                ->with('status', "GitHub reconnected as {$authorization->github_login}.");
        }

        $website = Website::query()->findOrFail(data_get($state, 'website_id'));
        $this->authorizeWebsite($request, $website);
        $installation = $this->storeInstallation((int) $data['installation_id'], $request);

        $oauthState = Crypt::encryptString(json_encode([
            'website_id' => $website->id,
            'user_id' => $request->user()->id,
            'installation_id' => $installation->id,
        ], JSON_THROW_ON_ERROR));

        return Redirect::away($this->oauth->authorizationUrl($oauthState));
    }

    protected function completeAuthorization(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $state = json_decode(Crypt::decryptString($data['state']), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(403, 'The GitHub authorization request is invalid.');
        }

        abort_unless(data_get($state, 'user_id') === $request->user()->id, 403);
        $website = Website::query()->findOrFail(data_get($state, 'website_id'));
        $this->authorizeWebsite($request, $website);
        $authorization = $this->oauth->authorize($request->user(), $data['code']);
        $installation = data_get($state, 'installation_id')
            ? GithubInstallation::query()->findOrFail(data_get($state, 'installation_id'))
            : null;
        $externalInstallationId = $installation?->installation_id ?? $request->integer('installation_id');

        abort_if($externalInstallationId < 1, 422, 'GitHub did not identify the installed App. Start the connection again.');
        abort_unless($this->oauth->canAccessInstallation($authorization, $externalInstallationId), 403, 'The GitHub user cannot access this App installation.');
        $installation ??= $this->storeInstallation($externalInstallationId, $request);

        return Redirect::route('admin.website-repositories.create', $website)
            ->with('status', "GitHub authorized as {$authorization->github_login} for {$installation->account_login}.");
    }

    protected function storeInstallation(int $installationId, Request $request): GithubInstallation
    {
        abort_if($installationId < 1, 422, 'GitHub did not identify the installed App. Start the connection again.');
        $githubInstallation = $this->github->installation($installationId);

        return GithubInstallation::query()->updateOrCreate(
            ['installation_id' => $githubInstallation['id']],
            [
                'installed_by' => $request->user()->id,
                'account_id' => data_get($githubInstallation, 'account.id'),
                'account_login' => data_get($githubInstallation, 'account.login'),
                'account_type' => data_get($githubInstallation, 'account.type'),
                'repository_selection' => $githubInstallation['repository_selection'],
                'permissions' => $githubInstallation['permissions'] ?? [],
                'status' => GithubInstallation::STATUS_ACTIVE,
                'suspended_at' => null,
            ],
        );
    }

    protected function authorizeWebsite(Request $request, Website $website): void
    {
        abort_unless($website->isManageableBy($request->user()), 403);
    }
}
