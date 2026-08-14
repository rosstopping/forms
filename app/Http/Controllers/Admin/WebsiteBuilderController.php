<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteBuildRequest;
use App\Models\GithubInstallation;
use App\Models\User;
use App\Services\WebsiteBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class WebsiteBuilderController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $installations = GithubInstallation::query()
            ->where('status', GithubInstallation::STATUS_ACTIVE)
            ->where('repository_selection', 'all')
            ->orderBy('account_login')
            ->get();
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.website-builder.create', compact('installations', 'users'));
    }

    public function store(StoreWebsiteBuildRequest $request, WebsiteBuilder $builder): RedirectResponse
    {
        try {
            $website = $builder->build($request->validated(), $request->user());
        } catch (\Throwable $exception) {
            report($exception);

            return Redirect::back()->withInput()->withErrors(['builder' => $exception->getMessage()]);
        }

        return Redirect::route('admin.websites.show', $website)
            ->with('status', 'The Eleventy website is connected to Netlify and Copilot is creating the full design in a pull request. Development URL: https://'.$website->primaryDomain()?->domain);
    }
}
