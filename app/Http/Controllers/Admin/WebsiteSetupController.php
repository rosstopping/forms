<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteSetupRequest;
use App\Http\Requests\UpdateWebsiteSetupRequest;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteSetup;
use App\Services\ContentSchedule;
use App\Services\WebsiteSetupService;
use App\Services\WordPressConnectionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsiteSetupController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('admin.websites.setup', [
            'website' => null, 'setup' => null, 'step' => 'business', 'values' => [],
            'steps' => WebsiteSetup::STEPS,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(StoreWebsiteSetupRequest $request, WebsiteSetupService $service): RedirectResponse
    {
        $website = $service->create($request->validated());

        return redirect()->route('admin.website-setup.edit', [$website, 'step' => 'goals'])->with('status', 'Client website created. Your setup progress is saved.');
    }

    public function edit(Request $request, Website $website, WebsiteSetupService $service, ContentSchedule $schedule, ?string $step = null): View|RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $setup = WebsiteSetup::query()->where('website_id', $website->id)->first();
        if ($step === null) {
            return redirect()->route('admin.website-setup.edit', [$website, 'step' => $setup?->current_step ?? 'business']);
        }
        abort_unless(array_key_exists($step, WebsiteSetup::STEPS), 404);
        $website->load(['domains', 'owner', 'members', 'contentPlan.creator.githubAuthorization', 'repository.installation', 'wordpressConnection', 'searchConsoleConnection', 'businessProfileConnection', 'seoTargetKeywords', 'competitors']);
        $values = $service->defaults($website);
        foreach ($setup?->data ?? [] as $key => $answers) {
            $values[$key] = array_replace($values[$key] ?? [], $answers);
        }
        $plan = $website->contentPlan?->replicate() ?? $website->contentPlan()->make(['created_by' => $request->user()->id]);
        $plan->enabled = true;
        $plan->setRelation('website', $website);

        return view('admin.websites.setup', [
            'website' => $website, 'setup' => $setup, 'step' => $step, 'values' => $values,
            'steps' => WebsiteSetup::STEPS,
            'contentReadiness' => $schedule->pauseReason($plan),
            'weeklyLimit' => $schedule->weeklyLimit($website),
        ]);
    }

    public function update(UpdateWebsiteSetupRequest $request, Website $website, string $step, WebsiteSetupService $service): RedirectResponse
    {
        if ($step === 'review') {
            $service->finish($website, $request->user());

            return redirect()->route('admin.website-setup.edit', [$website, 'step' => 'review'])->with('status', 'Setup saved and settings applied. Any outstanding connections are listed below.');
        }
        $setup = $service->save($website, $step, $request->validated());
        if ($request->input('navigation') === 'save') {
            $setup->update(['current_step' => $step]);

            return redirect()->route('admin.websites.index')->with('status', 'Setup progress saved. Use Client setup on the website to resume.');
        }

        return redirect()->route('admin.website-setup.edit', [$website, 'step' => $setup->current_step])->with('status', 'Step saved.');
    }

    public function pairing(Request $request, Website $website, WordPressConnectionManager $connections): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        if (! $website->repository()->exists()) {
            return back()->with('error', 'Connect the GitHub repository before pairing WordPress.');
        }
        $website->update(['wordpress_enabled' => true]);
        $pairing = $connections->issuePairingCode($website);

        return redirect()->route('admin.website-setup.edit', [$website, 'step' => 'connection'])
            ->with('wordpress_pairing_code', $pairing['code']);
    }
}
