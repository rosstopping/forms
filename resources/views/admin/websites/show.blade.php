@extends('layouts.app')

@section('content')
@php
    $primaryDomain = $website->domains->firstWhere('is_primary', true) ?? $website->domains->first();
@endphp
<div class="space-y-6" data-website-sections data-default-tab="{{ $currentWebsiteSection }}">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $website->name }}</h1>
            <p class="text-slate-600 text-base sm:text-sm">Audit health, search visibility, content activity, forms, and submissions.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (Auth::user()?->isAdmin())
                <a href="{{ route('admin.website-setup.edit', $website) }}" class="ui-button ui-button-secondary">Client setup</a>
                @if ($outreachProspect)
                    <a href="{{ route('admin.prospects.show', $outreachProspect) }}" class="ui-button ui-button-secondary">View outreach prospect</a>
                @else
                    <form method="POST" action="{{ route('admin.websites.prospect.store', $website) }}">
                        @csrf
                        <button type="submit" class="ui-button ui-button-secondary">Create outreach prospect</button>
                    </form>
                @endif
            @endif
            @if (Auth::user()?->isAdmin())
                <a href="{{ route('admin.websites.index') }}" class="ui-button ui-button-secondary">All websites</a>
            @endif
        </div>
    </div>

    @if ($primaryDomain && ! $primaryDomain->isVerified())
        <section class="flex flex-col gap-4 rounded-xl border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="ownership-title">
            <div>
                <p class="font-semibold uppercase tracking-widest text-amber-700 text-base sm:text-sm">Website ownership</p>
                <h2 id="ownership-title" class="mt-1 font-semibold text-slate-950">
                    {{ $primaryDomain->ownership_status === \App\Models\WebsiteDomain::OWNERSHIP_CONFLICT ? 'We need to review this website' : 'Verify that this is your website' }}
                </h2>
                <p class="mt-1 text-slate-700 text-base sm:text-sm">
                    @if ($primaryDomain->ownership_status === \App\Models\WebsiteDomain::OWNERSHIP_CONFLICT)
                        Search Console access was confirmed, but we could not safely complete verification automatically. No existing website data has been shared or moved.
                    @else
                        Your public website review is ready. Connect a matching owner property in Google Search Console to unlock ownership-dependent features.
                    @endif
                </p>
            </div>
            @if ($primaryDomain->ownership_status === \App\Models\WebsiteDomain::OWNERSHIP_PENDING && $canUseSearchConsole)
                <a href="{{ route('admin.search-console.connect', $website) }}" class="ui-button ui-button-secondary text-center">Verify with Google</a>
            @else
                <a href="{{ route('marketing.contact') }}" class="ui-button ui-button-secondary text-center">Contact a specialist</a>
            @endif
        </section>
    @endif

    @if ($website->copilot_build_task_id)
        <section class="flex flex-col gap-4 rounded-xl border border-violet-200 bg-violet-50 p-5 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="website-build-title">
            <div>
                <p class="font-semibold uppercase tracking-widest text-violet-700 text-base sm:text-sm">Website builder</p>
                <h2 id="website-build-title" class="mt-1 font-semibold text-slate-950">Your Eleventy design is being created</h2>
                <p class="mt-1 text-slate-600 text-base sm:text-sm">Task status: <span class="font-medium capitalize">{{ str_replace('_', ' ', $website->copilot_build_task_state ?: 'queued') }}</span>. Review and merge the pull request to publish through Netlify.</p>
            </div>
            @if ($website->copilot_build_task_url)
                <a href="{{ $website->copilot_build_task_url }}" target="_blank" rel="noopener" class="ui-button ui-button-primary">Open build task</a>
            @endif
        </section>
    @endif

    <nav class="hidden" aria-label="Website sections">
        <a href="{{ \App\Support\WebsiteNavigation::routeFor($website, 'health') }}" id="website-tab-health" data-tab="health">Health reports</a>
        <a href="{{ \App\Support\WebsiteNavigation::routeFor($website, 'search') }}" id="website-tab-search" data-tab="search">Search</a>
        <a href="{{ \App\Support\WebsiteNavigation::routeFor($website, 'seo') }}" id="website-tab-seo" data-tab="seo">SEO Intelligence</a>
        <a href="{{ \App\Support\WebsiteNavigation::routeFor($website, 'content') }}" id="website-tab-content" data-tab="content">Content</a>
        @if ($website->wordpress_enabled)
            <a href="{{ \App\Support\WebsiteNavigation::routeFor($website, 'wordpress') }}" id="website-tab-wordpress" data-tab="wordpress">WordPress</a>
        @endif
        @if (config('forms.pixel_ui_enabled') && $canUseGrowthFeatures && $website->pixel_enabled)
            <a href="{{ \App\Support\WebsiteNavigation::routeFor($website, 'pixel') }}" id="website-tab-pixel" data-tab="pixel">Pixel</a>
        @endif
        <a href="{{ \App\Support\WebsiteNavigation::routeFor($website, 'business-profile') }}" id="website-tab-business-profile" data-tab="business-profile">Business Profile</a>
        <a href="{{ \App\Support\WebsiteNavigation::routeFor($website, 'forms') }}" id="website-tab-forms" data-tab="forms">Forms</a>
        <a href="{{ \App\Support\WebsiteNavigation::routeFor($website, 'settings') }}" id="website-tab-settings" data-tab="settings">Settings</a>
    </nav>

    <div id="website-panel-health" class="space-y-6" role="region" aria-labelledby="website-tab-health" data-tab-panel="health" @if ($currentWebsiteSection !== 'health') hidden @endif>
        @php
            $latestReport = $website->healthReports->first();
        @endphp
        <section class="ui-panel @container" aria-labelledby="health-title">
        <div class="flex flex-col gap-4 border-b border-slate-950/10 p-5 @2xl:flex-row @2xl:items-start @2xl:justify-between sm:p-6">
            <div>
                <p class="font-mono text-teal-700 text-base sm:text-sm">Website monitoring</p>
                <h2 id="health-title" class="mt-1 text-lg font-semibold text-slate-950">Health reports</h2>
                <p class="mt-1 text-base text-slate-600 sm:text-sm">Availability, on-page SEO, security headers, discoverability, and form delivery.</p>
            </div>
            @if ($canManageWebsite && $canRunHealthReports)
                <form method="POST" action="{{ route('admin.website-health-reports.store', $website) }}">
                    @csrf
                    <button type="submit" @class(['ui-button', 'ui-button-secondary' => $latestReport, 'ui-button-primary' => ! $latestReport])>Run report now</button>
                </form>
            @endif
        </div>
        @unless ($canRunHealthReports)
            <div class="border-b border-slate-950/10 p-5 sm:p-6">
                <x-feature-upgrade-banner tier="Essential" title="Keep monitoring your website" description="An active Sitewell plan includes manual and scheduled website health reports." />
            </div>
        @endunless
        @if ($latestReport)
            <dl class="grid grid-cols-2 gap-px bg-slate-950/10 @2xl:grid-cols-4">
                <div class="bg-white p-4"><dt class="truncate text-sm text-slate-500">Latest status</dt><dd class="mt-1 text-xl font-semibold capitalize">{{ str_replace('_', ' ', $latestReport->overall_status ?: $latestReport->status) }}</dd></div>
                <div class="bg-white p-4"><dt class="truncate text-sm text-slate-500">Passed</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-emerald-700">{{ $latestReport->passed_checks }}</dd></div>
                <div class="bg-white p-4"><dt class="truncate text-sm text-slate-500">Warnings</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-amber-700">{{ $latestReport->warning_checks }}</dd></div>
                <div class="bg-white p-4"><dt class="truncate text-sm text-slate-500">Failed</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-red-700">{{ $latestReport->failed_checks }}</dd></div>
            </dl>
            <div class="flex flex-col gap-4 p-5 @lg:flex-row @lg:items-end @lg:justify-between sm:p-6">
                <label class="ui-label block min-w-0 @lg:min-w-80">
                    <span class="block text-base font-medium text-slate-700 sm:text-sm">Previous reports</span>
                    <select data-health-report-selector class="ui-input mt-1 block w-full" aria-label="Select a website health report">
                        @foreach ($website->healthReports as $historicalReport)
                            <option value="{{ route('admin.website-health-reports.show', [$website, $historicalReport]) }}">{{ $historicalReport->created_at->format('j M Y, H:i') }} · {{ ucfirst(str_replace('_', ' ', $historicalReport->overall_status ?: $historicalReport->status)) }}{{ $loop->first ? ' · Latest' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <a href="{{ route('admin.website-health-reports.show', [$website, $latestReport]) }}" class="ui-button ui-button-primary">View latest report</a>
            </div>
        @else
            <p class="p-5 text-base text-slate-600 sm:p-6 sm:text-sm">No health reports have been generated yet.</p>
        @endif
        </section>

    </div>

    @if ($canUseSearchConsole)
    <div id="website-panel-search" class="space-y-6" role="region" aria-labelledby="website-tab-search" data-tab-panel="search" @if ($currentWebsiteSection !== 'search') hidden @endif>
        <section class="ui-panel p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-medium uppercase tracking-wide text-slate-500 text-base sm:text-sm">Content intelligence</p>
                    <h2 class="mt-1 font-semibold">Google Search Console</h2>
                    <p class="mt-1 text-slate-600 text-base sm:text-sm">{{ $website->searchConsoleConnection?->property_url ?: 'Connect a property to use real search queries, clicks, impressions, and rankings.' }}</p>
                </div>
                @if ($website->searchConsoleConnection)
                    <form method="POST" action="{{ route('admin.search-console.destroy', $website) }}">@csrf @method('DELETE')<button type="submit" class="ui-button ui-button-secondary">Disconnect</button></form>
                @else
                    <a href="{{ route('admin.search-console.connect', $website) }}" class="ui-button ui-button-primary">Connect Google</a>
                @endif
            </div>
            @if ($searchConsoleReport)
                <div class="mt-5 border-t border-slate-950/10 pt-5">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <h3 class="font-medium text-slate-900">Search performance</h3>
                        <div class="flex items-center gap-3 text-xs">
                            <p class="text-slate-500">{{ \Illuminate\Support\Carbon::parse($searchConsoleReport['period']['start'])->format('j M') }}–{{ \Illuminate\Support\Carbon::parse($searchConsoleReport['period']['end'])->format('j M Y') }}</p>
                            <a href="{{ route('admin.search-console.performance', $website) }}" class="font-medium text-slate-900 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-900">View all data</a>
                        </div>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div class="ui-well p-3"><dt class="text-xs text-slate-500">Clicks</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['clicks']) }}</dd></div>
                        <div class="ui-well p-3"><dt class="text-xs text-slate-500">Impressions</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['impressions']) }}</dd></div>
                        <div class="ui-well p-3"><dt class="text-xs text-slate-500">Average CTR</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['ctr'] * 100, 1) }}%</dd></div>
                        <div class="ui-well p-3"><dt class="text-xs text-slate-500">Average position</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['position'], 1) }}</dd></div>
                    </dl>
                    <div class="mt-5 grid gap-4">
                        <x-comparison-chart title="Clicks and impressions" description="Monthly visits from Google compared with appearances in search results." :points="$searchConsoleHistory" first-key="clicks" first-label="Clicks" second-key="impressions" second-label="Impressions" />
                        <x-progress-chart title="Average position" description="Impression-weighted average Google position; lower is better." :points="$searchConsoleHistory" value-key="position" format="decimal" :lower-is-better="true" />
                    </div>
                    <div class="mt-5 grid gap-5 lg:grid-cols-2">
                        <div class="overflow-x-auto">
                            <h4 class="text-sm font-medium text-slate-900">Top queries</h4>
                            <table class="mt-2 min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2 pr-3">Query</th><th class="py-2 text-right">Clicks</th><th class="py-2 pl-3 text-right">Position</th></tr></thead><tbody>@forelse ($searchConsoleReport['queries'] as $query)<tr class="border-b border-slate-100"><td class="max-w-64 truncate py-2 pr-3" title="{{ $query['query'] }}"><a href="{{ route('admin.search-console.queries.show', [$website, 'query' => $query['query']]) }}" class="text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:decoration-teal-700">{{ $query['query'] }}</a></td><td class="py-2 text-right tabular-nums">{{ number_format($query['clicks']) }}</td><td class="py-2 pl-3 text-right tabular-nums">{{ number_format($query['position'], 1) }}</td></tr>@empty<tr><td colspan="3" class="py-3 text-slate-500">No query data yet.</td></tr>@endforelse</tbody></table>
                        </div>
                        <div class="overflow-x-auto">
                            <h4 class="text-sm font-medium text-slate-900">Top pages</h4>
                            <table class="mt-2 min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2 pr-3">Page</th><th class="py-2 text-right">Clicks</th><th class="py-2 pl-3 text-right">Position</th></tr></thead><tbody>@forelse ($searchConsoleReport['pages'] as $page)<tr class="border-b border-slate-100"><td class="max-w-64 truncate py-2 pr-3" title="{{ $page['page'] }}">{{ \Illuminate\Support\Str::after($page['page'], '://') }}</td><td class="py-2 text-right tabular-nums">{{ number_format($page['clicks']) }}</td><td class="py-2 pl-3 text-right tabular-nums">{{ number_format($page['position'], 1) }}</td></tr>@empty<tr><td colspan="3" class="py-3 text-slate-500">No page data yet.</td></tr>@endforelse</tbody></table>
                        </div>
                    </div>
                </div>
            @elseif ($searchConsoleReportUnavailable)
                <p class="mt-4 border-t border-slate-950/10 pt-4 text-amber-700 text-base sm:text-sm">Search performance is temporarily unavailable. The rest of the dashboard is unaffected.</p>
            @endif
        </section>

        @if ($canUseGrowthFeatures)
            @include('admin.websites.partials.search-opportunities')
        @endif
    </div>

    @else
    <div id="website-panel-search" class="space-y-6" role="region" aria-labelledby="website-tab-search" data-tab-panel="search" @if ($currentWebsiteSection !== 'search') hidden @endif>
        <x-feature-upgrade-banner tier="Essential" title="Connect Google Search Console" description="An active Sitewell plan lets you connect Google Search Console and see clicks, impressions, rankings, and the searches people use to find your website." />
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Search performance preview">
            @foreach (['Clicks and impressions', 'Average position', 'Top customer searches', 'Best-performing pages'] as $feature)
                <div class="ui-panel p-4"><div class="h-2 w-16 rounded-full bg-violet-100"></div><h3 class="mt-4 font-semibold text-slate-900">{{ $feature }}</h3><p class="mt-1 text-slate-500 text-base sm:text-sm">Available with an active Sitewell plan.</p></div>
            @endforeach
        </div>
    </div>
    @endif

    @if ($canUseGrowthFeatures)
    @include('admin.websites.partials.seo-intelligence', [
        'website' => $website,
        'seoGeneration' => $seoGeneration,
        'seoSnapshot' => $seoSnapshot,
        'seoKeywords' => $seoKeywords,
        'seoReferringDomains' => $seoReferringDomains,
        'seoCompetitors' => $seoCompetitors,
        'seoOpportunities' => $seoOpportunities,
        'seoFilter' => $seoFilter,
        'seoSort' => $seoSort,
        'seoDirection' => $seoDirection,
        'strikingDistanceCount' => $strikingDistanceCount,
        'canManageWebsite' => $canManageWebsite,
        'dataForSeoConfigured' => $dataForSeoConfigured,
    ])

    @else
    <div id="website-panel-seo" class="space-y-6" role="region" aria-labelledby="website-tab-seo" data-tab-panel="seo" @if ($currentWebsiteSection !== 'seo') hidden @endif>
        <x-feature-upgrade-banner tier="Growth" title="See where your website can grow" description="SEO Intelligence tracks keyword visibility, competitors, backlinks, and prioritised recommendations so you know what to improve next." />
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ([['Keyword opportunities', 'Find valuable searches where a focused improvement could move your website up.'], ['Competitor visibility', 'Compare the businesses competing for the same searches and customers.'], ['Recommended actions', 'Turn SEO evidence into a practical, prioritised improvement list.']] as [$feature, $description])
                <article class="ui-panel ui-section"><span class="text-xs font-semibold uppercase tracking-widest text-violet-700">SEO Intelligence</span><h3 class="mt-2 font-semibold text-slate-950">{{ $feature }}</h3><p class="mt-2 text-slate-600 text-base sm:text-sm">{{ $description }}</p></article>
            @endforeach
        </div>
    </div>
    @endif

    @include('admin.websites.partials.content')

    @if ($website->wordpress_enabled)
        <div id="website-panel-wordpress" class="space-y-6" role="region" aria-labelledby="website-tab-wordpress" data-tab-panel="wordpress" @if ($currentWebsiteSection !== 'wordpress') hidden @endif>
            @include('admin.websites.partials.wordpress-connection')
        </div>
    @endif

    @if (config('forms.pixel_ui_enabled') && $canUseGrowthFeatures && $website->pixel_enabled)
        @include('admin.websites.partials.pixel', [
            'website' => $website,
            'pixelInstallationSnippet' => $pixelInstallationSnippet,
        ])
    @endif

    @if ($canUseCompleteFeatures)
        @include('admin.websites.partials.business-profile')
    @else
        <div id="website-panel-business-profile" class="space-y-6" role="region" aria-labelledby="website-tab-business-profile" data-tab-panel="business-profile" @if ($currentWebsiteSection !== 'business-profile') hidden @endif>
            <x-feature-upgrade-banner tier="Complete" title="Put your local presence to work" description="Upgrade to Complete for Google Business Profile health checks, recommended changes, generated post drafts, and approval-first review replies." />
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ([['Profile health checks', 'Spot missing or outdated details and receive practical recommendations.'], ['Google post drafts', 'Keep your profile active with useful, reviewable post ideas.'], ['Review reply assistance', 'Prepare thoughtful responses while keeping every reply under your control.'], ['Advanced automations', 'Keep profile checks and drafts moving without adding another manual routine.']] as [$feature, $description])
                    <article class="ui-panel ui-section"><span class="text-xs font-semibold uppercase tracking-widest text-violet-700">Google Business Profile</span><h3 class="mt-2 font-semibold text-slate-950">{{ $feature }}</h3><p class="mt-2 text-slate-600 text-base sm:text-sm">{{ $description }}</p></article>
                @endforeach
            </div>
        </div>
    @endif

    <div id="website-panel-settings" class="space-y-6" role="region" aria-labelledby="website-tab-settings" data-tab-panel="settings" @if ($currentWebsiteSection !== 'settings') hidden @endif>
        <section class="ui-panel overflow-hidden" aria-labelledby="website-settings-title">
            <div class="border-b border-slate-950/10 p-5 sm:p-6">
                <p class="font-mono text-teal-700 text-base sm:text-sm">Website settings</p>
                <h2 id="website-settings-title" class="mt-1 text-xl font-semibold text-slate-950">Your website details</h2>
                <p class="mt-1 text-base text-slate-600 sm:text-sm">Choose the name Sitewell uses for this website throughout your account.</p>
            </div>

            @if ($canManageWebsite)
                <form method="POST" action="{{ route('admin.websites.update', $website) }}" class="flex flex-col gap-4 p-5 sm:p-6">
                    @csrf
                    @method('PUT')
                    <div class="max-w-xl">
                        <label class="ui-label" for="name">Website name</label>
                        <input id="name" name="name" type="text" required value="{{ old('name', $website->name) }}" class="ui-input mt-1 w-full">
                        @error('name')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                    </div>
                    <div><button type="submit" class="ui-button ui-button-primary">Save name</button></div>
                </form>
            @endif

            @if (Auth::user()?->isAdmin())
                <details class="border-t border-slate-950/10">
                    <summary class="cursor-pointer px-5 py-4 text-base font-medium text-slate-800 hover:bg-slate-50 sm:px-6 sm:text-sm">Advanced website settings</summary>
                    <form method="POST" action="{{ route('admin.websites.update', $website) }}" class="space-y-6 border-t border-slate-950/10 p-5 sm:p-6">
                        @csrf
                        @method('PUT')
                    <fieldset class="space-y-3 border-t border-slate-950/10 pt-6">
                        <legend class="text-sm font-semibold text-slate-950">Connection workspaces</legend>
                        <p class="text-slate-600 text-base sm:text-sm">Choose which connection tabs are available for this website.</p>
                        <label class="ui-label flex items-start gap-3 rounded-lg border border-slate-950/10 p-4">
                            <input type="hidden" name="wordpress_enabled" value="0">
                            <input type="checkbox" name="wordpress_enabled" value="1" class="mt-1" @checked(old('wordpress_enabled', $website->wordpress_enabled))>
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Enable the WordPress connection</span>
                                <span class="block text-xs leading-5 text-slate-500">Shows the WordPress tab for pairing the plugin and managing website releases.</span>
                            </span>
                        </label>
                        @if (config('forms.pixel_ui_enabled'))
                            <label class="ui-label flex items-start gap-3 rounded-lg border border-slate-950/10 p-4">
                                <input type="hidden" name="pixel_enabled" value="0">
                                <input type="checkbox" name="pixel_enabled" value="1" class="mt-1" @checked(old('pixel_enabled', $website->pixel_enabled))>
                                <span>
                                    <span class="block text-sm font-medium text-slate-900">Enable the Pixel connection</span>
                                    <span class="block text-xs leading-5 text-slate-500">Shows the Pixel tab and allows approved Pixel changes to be delivered to the website.</span>
                                </span>
                            </label>
                        @endif
                    </fieldset>
                    <label class="ui-label flex items-start gap-3 rounded-lg border border-slate-950/10 p-3">
                        <input type="hidden" name="health_reports_enabled" value="0">
                        <input type="checkbox" name="health_reports_enabled" value="1" class="mt-1" @checked($website->health_reports_enabled)>
                        <span>
                            <span class="block text-sm font-medium text-slate-900">Send weekly website health reports</span>
                            <span class="block text-xs text-slate-500">Reports are emailed to administrators and assigned website users.</span>
                        </span>
                    </label>
                    <div class="space-y-3 rounded-lg border border-slate-950/10 p-3">
                        <label class="ui-label flex items-start gap-3">
                            <input type="hidden" name="webhook_enabled" value="0">
                            <input type="checkbox" name="webhook_enabled" value="1" class="mt-1" @checked(old('webhook_enabled', $website->webhook_enabled))>
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Send submissions to a webhook</span>
                                <span class="block text-xs text-slate-500">New form submissions will be posted to the URL below unless a form has its own override.</span>
                            </span>
                        </label>
                        <div>
                            <label class="ui-label block" for="webhook_url">Webhook URL</label>
                            <input id="webhook_url" name="webhook_url" type="url" value="{{ old('webhook_url', $website->webhook_url) }}" placeholder="https://example.com/webhooks/forms" class="ui-input mt-1 w-full">
                            @error('webhook_url')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="ui-label block" for="webhook_secret">Webhook secret</label>
                            <input id="webhook_secret" name="webhook_secret" type="text" value="{{ old('webhook_secret', $website->webhook_secret) }}" autocomplete="off" class="ui-input mt-1 w-full">
                            <p class="mt-1 text-slate-500 text-base sm:text-sm">Optional secret used to sign webhook requests.</p>
                            @error('webhook_secret')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="space-y-3 rounded-lg border border-slate-950/10 p-3">
                        <label class="ui-label flex items-start gap-3">
                            <input type="hidden" name="turnstile_enabled" value="0">
                            <input type="checkbox" name="turnstile_enabled" value="1" class="mt-1" @checked(old('turnstile_enabled', $website->turnstile_enabled))>
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Protect submissions with Cloudflare Turnstile</span>
                                <span class="block text-xs text-slate-500">Submissions that fail verification are silently quarantined as spam.</span>
                            </span>
                        </label>
                        <div>
                            <label class="ui-label block" for="turnstile_site_key">Turnstile site key</label>
                            <input id="turnstile_site_key" name="turnstile_site_key" type="text" value="{{ old('turnstile_site_key', $website->turnstile_site_key) }}" autocomplete="off" class="ui-input mt-1 w-full font-mono">
                            @error('turnstile_site_key')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="ui-label block" for="turnstile_secret_key">Turnstile secret key</label>
                            <input id="turnstile_secret_key" name="turnstile_secret_key" type="password" value="" placeholder="{{ $website->turnstile_secret_key ? 'Configured — leave blank to keep it' : '' }}" autocomplete="new-password" class="ui-input mt-1 w-full font-mono">
                            <p class="mt-1 text-slate-500 text-base sm:text-sm">Stored server-side and never included in the website form.</p>
                            @error('turnstile_secret_key')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                        </div>
                    </div>
                        <button type="submit" class="ui-button ui-button-secondary">Save advanced settings</button>
                    </form>
                </details>

                <div class="border-t border-red-200 px-5 py-5 sm:px-6">
                    <h3 class="text-sm font-semibold text-red-900">Delete website</h3>
                    <p class="mt-1 text-red-700 text-base sm:text-sm">This permanently deletes the website, its forms, submissions, reports, and content settings.</p>
                    <form method="POST" action="{{ route('admin.websites.destroy', $website) }}" class="mt-3" onsubmit="return confirm('Delete this website and all of its data? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="ui-button ui-button-danger">Delete website</button>
                    </form>
                </div>
            @endif
        </section>

        <section class="ui-panel ui-section">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold">Website users</h2>
                    <p class="mt-1 text-slate-600 text-base sm:text-sm">Managers can make changes. Viewers have read-only access.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-700">{{ $websiteUsers->count() }} {{ Str::plural('user', $websiteUsers->count()) }}</span>
            </div>

            @if (Auth::user()?->isAdmin())
                <form method="POST" action="{{ route('admin.websites.update', $website) }}" class="ui-well mt-4 p-4">
                    @csrf
                    @method('PUT')
                    <label for="subscription_user_id" class="ui-label block">Subscription account</label>
                    <p class="mt-1 text-slate-600 text-base sm:text-sm">This member’s package unlocks the website’s features. Their Viewer or Manager access stays unchanged. Your administrator access lets you manage the website for them.</p>
                    <select id="subscription_user_id" name="subscription_user_id" class="ui-input mt-3 w-full">
                        <option value="">No subscription account</option>
                        @foreach ($websiteUsers as $websiteUser)
                            <option value="{{ $websiteUser['user']->id }}" @selected((string) old('subscription_user_id', $website->user_id) === (string) $websiteUser['user']->id)>{{ $websiteUser['user']->name }} — {{ $websiteUser['user']->email }}</option>
                        @endforeach
                    </select>
                    @error('subscription_user_id')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                    <button type="submit" class="ui-button ui-button-secondary mt-3">Save subscription account</button>
                </form>
            @endif

            @error('role')
                <p class="mt-4 rounded-lg bg-red-50 p-3 text-base text-red-700 sm:text-sm" role="alert">{{ $message }}</p>
            @enderror

            <div class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-950/10">
                @foreach ($websiteUsers as $websiteUser)
                    @php
                        $member = $websiteUser['user'];
                        $memberRole = $websiteUser['role'];
                        $isOnlyManager = $member->id === $soleManagerId;
                    @endphp
                    <div class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-base font-medium text-slate-900 sm:text-sm">{{ $member->name }}</p>
                            <p class="truncate text-base text-slate-500 sm:text-sm">{{ $member->email }}</p>
                            @if (Auth::user()?->isAdmin())
                                <details class="mt-2 rounded-lg border border-slate-950/10 p-3">
                                    <summary class="cursor-pointer text-sm font-medium text-slate-700">Manage membership</summary>
                                    <form method="POST" action="{{ route('admin.websites.members.update', [$website, $member]) }}" class="mt-3">
                                        @csrf
                                        @method('PUT')
                                        @include('admin.websites.partials.member-membership-fields', [
                                            'membershipFormKey' => 'member_'.$member->id,
                                            'membershipTier' => $member->admin_membership_tier,
                                            'membershipEndsOn' => $member->admin_membership_expires_at?->format('Y-m-d'),
                                            'membershipRequired' => true,
                                        ])
                                        <button type="submit" class="ui-button ui-button-secondary mt-3">Save membership</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                        @if ($canManageMembers && ! $isOnlyManager)
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('admin.websites.members.update', [$website, $member]) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <label for="member_role_{{ $member->id }}" class="ui-label sr-only">Access for {{ $member->name }}</label>
                                    <select id="member_role_{{ $member->id }}" name="role" class="ui-input">
                                        <option value="manager" @selected($memberRole === 'manager')>Manager</option>
                                        <option value="viewer" @selected($memberRole === 'viewer')>Viewer</option>
                                    </select>
                                    <button type="submit" class="ui-button ui-button-secondary ui-button-small">Update</button>
                                </form>
                                <form method="POST" action="{{ route('admin.websites.members.destroy', [$website, $member]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ui-button ui-button-danger ui-button-small">Remove</button>
                                </form>
                            </div>
                        @else
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium capitalize text-slate-700">{{ $memberRole }}</span>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($canManageMembers && $canUseGrowthFeatures)
                <form method="POST" action="{{ route('admin.websites.members.store', $website) }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end">
                    @csrf
                    <div><label for="member_email" class="ui-label">Invite by email</label><input id="member_email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" placeholder="colleague@example.com" class="ui-input mt-1 w-full"><p class="mt-1 text-slate-500 text-base sm:text-sm">We’ll email them a secure link to set up their account.</p>@error('email')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror</div>
                    <div><label for="member_role" class="ui-label">Access</label><select id="member_role" name="role" class="ui-input mt-1 w-full"><option value="manager" @selected(old('role', 'viewer') === 'manager')>Manager</option><option value="viewer" @selected(old('role', 'viewer') === 'viewer')>Viewer</option></select></div>
                    @if (Auth::user()?->isAdmin())
                        <div class="ui-well p-4 sm:col-span-3">
                            @include('admin.websites.partials.member-membership-fields', [
                                'membershipFormKey' => 'invite',
                                'membershipTier' => null,
                                'membershipEndsOn' => now()->addMonthsNoOverflow(6)->format('Y-m-d'),
                                'membershipRequired' => false,
                            ])
                        </div>
                    @endif
                    <button type="submit" class="ui-button ui-button-secondary">Send invitation</button>
                </form>
            @elseif ($canManageMembers)
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-800 text-base sm:text-sm">A Growth or Complete membership is required to invite additional website users.</p>
            @endif
        </section>
    </div>

    @include('admin.websites.partials.forms')

    <details id="website-assistant" @if (request('assistant') === 'open' || $errors->has('question')) open @endif class="ui-panel group fixed right-3 bottom-3 left-3 z-50 overflow-hidden open:border-slate-950/10 sm:right-6 sm:bottom-6 sm:left-auto sm:w-[26rem]">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-slate-950 px-4 py-3 text-white select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-500 [&::-webkit-details-marker]:hidden">
            <span class="flex items-center gap-3">
                <span class="grid size-9 place-items-center rounded-full bg-teal-400 text-slate-950" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path d="M7 18.5 3.5 21v-5A8.5 8.5 0 1 1 7 18.5Z" stroke-linejoin="round"/><path d="M8 10h8M8 14h5" stroke-linecap="round"/></svg>
                </span>
                <span><span class="block text-sm font-semibold">Ask Sitewell</span><span class="block text-xs text-slate-400">About {{ $website->name }}</span></span>
            </span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5 transition-transform group-open:rotate-180" aria-hidden="true"><path d="m6 15 6-6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </summary>

        <div class="flex max-h-[calc(100dvh-7rem)] flex-col bg-white">
            @if ($canUseCompleteFeatures)
                <div class="flex items-center justify-between gap-3 border-b border-slate-950/10 px-4 py-3">
                    <p class="leading-5 text-slate-600 text-base sm:text-sm">Restricted to this website’s stored reports and search data.</p>
                    <span class="shrink-0 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-teal-800">{{ max(0, $websiteAiWeeklyLimit - $websiteAiQuestionsUsed) }}/{{ $websiteAiWeeklyLimit }} left</span>
                </div>

                <div data-website-ai-messages class="min-h-24 flex-1 divide-y divide-slate-100 overflow-y-auto overscroll-contain">
                    @forelse ($websiteAiQuestions->reverse() as $websiteAiQuestion)
                        <article data-website-ai-question data-status-url="{{ route('admin.websites.assistant.questions.show', [$website, $websiteAiQuestion]) }}" data-status="{{ $websiteAiQuestion->status }}" class="space-y-2 px-4 py-3">
                            <div class="ui-well ml-8 rounded-br-md px-3 py-2 text-sm text-slate-800">{{ $websiteAiQuestion->question }}</div>
                            @if ($websiteAiQuestion->status === 'completed')
                                <p data-website-ai-response class="mr-8 whitespace-pre-line rounded-2xl rounded-bl-md bg-teal-50 px-3 py-2 leading-6 text-slate-700 text-base sm:text-sm">{{ $websiteAiQuestion->answer }}</p>
                            @elseif ($websiteAiQuestion->status === 'failed')
                                <p data-website-ai-response class="mr-8 rounded-2xl rounded-bl-md bg-red-50 px-3 py-2 text-red-700 text-base sm:text-sm">{{ $websiteAiQuestion->error }}</p>
                            @else
                                <p data-website-ai-response class="text-slate-500 text-base sm:text-sm">Preparing an answer…</p>
                            @endif
                            @if (in_array($websiteAiQuestion->status, ['completed', 'failed'], true))
                                @if ($websiteAiQuestion->reported_at)
                                    <p class="px-1 text-right text-[11px] font-medium text-amber-700">Reported for investigation</p>
                                @else
                                    <details class="group/report px-1 text-right">
                                        <summary class="cursor-pointer list-none text-[11px] text-slate-500 hover:text-slate-800 [&::-webkit-details-marker]:hidden">This answer wasn’t helpful?</summary>
                                        <form method="POST" action="{{ route('admin.websites.assistant.questions.report', [$website, $websiteAiQuestion]) }}" class="ui-well mt-2 space-y-2 p-3 text-left">
                                            @csrf
                                            <label for="website-assistant-report-{{ $websiteAiQuestion->id }}" class="ui-label block">What should it have understood? <span class="font-normal text-slate-500">Optional</span></label>
                                            <textarea id="website-assistant-report-{{ $websiteAiQuestion->id }}" name="reason" rows="2" maxlength="1000" placeholder="Tell us what you expected…" class="ui-input w-full resize-none leading-5 placeholder:text-slate-400"></textarea>
                                            <div class="flex items-center justify-between gap-3"><p class="text-[11px] text-slate-500">Our team will receive the question and response.</p><button type="submit" class="ui-button ui-button-secondary">Report answer</button></div>
                                        </form>
                                    </details>
                                @endif
                            @endif
                        </article>
                    @empty
                        <div data-website-ai-empty class="space-y-3 p-5 text-center">
                            <div><p class="font-medium text-slate-900 text-base sm:text-sm">What would you like to understand?</p><p class="mt-1 leading-5 text-slate-500 text-base sm:text-sm">Ask about this website’s rankings, search performance, opportunities, or health reports.</p></div>
                            <div class="space-y-1.5 text-left text-xs text-slate-600">
                                <p class="font-semibold text-slate-700">Try asking:</p>
                                <p class="rounded-lg bg-slate-50 px-3 py-2">“Which keywords have improved or declined recently?”</p>
                                <p class="rounded-lg bg-slate-50 px-3 py-2">“What should we prioritise from the latest health report?”</p>
                                <p class="rounded-lg bg-slate-50 px-3 py-2">“How have clicks and impressions changed over the last six months?”</p>
                            </div>
                        </div>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('admin.websites.assistant.questions.store', $website) }}" data-website-ai-form class="space-y-2 border-t border-slate-950/10 p-3">
                    @csrf
                    <label for="website-assistant-question" class="ui-label sr-only">Ask a question about {{ $website->name }}</label>
                    <textarea id="website-assistant-question" name="question" rows="2" maxlength="1000" required placeholder="Ask about this website…" class="ui-input w-full resize-none leading-5 placeholder:text-slate-400">{{ old('question') }}</textarea>
                    @error('question')<p class="text-red-600 text-base sm:text-sm">{{ $message }}</p>@enderror
                    <p data-website-ai-error class="hidden text-red-600 text-base sm:text-sm" role="alert"></p>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[11px] text-slate-500">Resets Monday · Out-of-scope requests count</p>
                        <button type="submit" @disabled($websiteAiQuestionsUsed >= $websiteAiWeeklyLimit) class="rounded-lg bg-teal-600 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:bg-slate-300">Send</button>
                    </div>
                </form>
            @else
                <div class="space-y-4 p-5">
                    <div><span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-800">Complete feature</span><h2 class="mt-3 font-semibold text-slate-950">Ask questions about your website data</h2><p class="mt-2 leading-6 text-slate-600 text-base sm:text-sm">Upgrade to Complete to ask about health reports, Search Console performance, SEO rankings, and opportunities for this website.</p></div>
                    <a href="{{ route('admin.billing.index') }}" class="ui-button ui-button-primary block text-center">View packages</a>
                </div>
            @endif
        </div>
    </details>
</div>
@endsection
