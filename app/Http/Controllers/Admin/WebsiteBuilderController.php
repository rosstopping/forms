<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteBuildRequest;
use App\Jobs\BuildWebsite;
use App\Models\GithubInstallation;
use App\Models\User;
use App\Models\WebsiteBuild;
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
        $builds = WebsiteBuild::query()
            ->whereBelongsTo($request->user(), 'requester')
            ->with('website')
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.website-builder.create', compact('installations', 'users', 'builds'));
    }

    public function store(StoreWebsiteBuildRequest $request): RedirectResponse
    {
        $build = WebsiteBuild::query()->create([
            'requested_by' => $request->user()->id,
            'details' => $request->validated(),
        ]);

        BuildWebsite::dispatch($build->id)->afterCommit();

        return Redirect::route('admin.website-builder.create')
            ->with('status', 'The website build has been queued. You can safely leave this page while GitHub, Netlify, and the design automation finish in the background.');
    }
}
