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
            <p class="text-sm text-slate-600">Audit health, search visibility, content activity, forms, and submissions.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (Auth::user()?->isAdmin())
                @if ($outreachProspect)
                    <a href="{{ route('admin.prospects.show', $outreachProspect) }}" class="rounded-md bg-teal-600 px-3 py-2 text-sm font-medium text-white hover:bg-teal-700">View outreach prospect</a>
                @else
                    <form method="POST" action="{{ route('admin.websites.prospect.store', $website) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-teal-600 px-3 py-2 text-sm font-medium text-white hover:bg-teal-700">Create outreach prospect</button>
                    </form>
                @endif
            @endif
            @if (Auth::user()?->isAdmin())
                <a href="{{ route('admin.websites.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">All websites</a>
            @endif
        </div>
    </div>

    @if ($primaryDomain && ! $primaryDomain->isVerified())
        <section class="flex flex-col gap-4 rounded-xl border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="ownership-title">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-amber-700">Website ownership</p>
                <h2 id="ownership-title" class="mt-1 font-semibold text-slate-950">
                    {{ $primaryDomain->ownership_status === \App\Models\WebsiteDomain::OWNERSHIP_CONFLICT ? 'We need to review this website' : 'Verify that this is your website' }}
                </h2>
                <p class="mt-1 text-sm text-slate-700">
                    @if ($primaryDomain->ownership_status === \App\Models\WebsiteDomain::OWNERSHIP_CONFLICT)
                        Search Console access was confirmed, but we could not safely complete verification automatically. No existing website data has been shared or moved.
                    @else
                        Your public website review is ready. Connect a matching owner property in Google Search Console to unlock ownership-dependent features.
                    @endif
                </p>
            </div>
            @if ($primaryDomain->ownership_status === \App\Models\WebsiteDomain::OWNERSHIP_PENDING && $canUseSearchConsole)
                <a href="{{ route('admin.search-console.connect', $website) }}" class="shrink-0 rounded-lg bg-amber-700 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-amber-800">Verify with Google</a>
            @else
                <a href="{{ route('marketing.contact') }}" class="shrink-0 rounded-lg border border-amber-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-amber-900 hover:bg-amber-100">Contact a specialist</a>
            @endif
        </section>
    @endif

    @if ($website->copilot_build_task_id)
        <section class="flex flex-col gap-4 rounded-xl border border-violet-200 bg-violet-50 p-5 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="website-build-title">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-violet-700">Website builder</p>
                <h2 id="website-build-title" class="mt-1 font-semibold text-slate-950">Your Eleventy design is being created</h2>
                <p class="mt-1 text-sm text-slate-600">Task status: <span class="font-medium capitalize">{{ str_replace('_', ' ', $website->copilot_build_task_state ?: 'queued') }}</span>. Review and merge the pull request to publish through Netlify.</p>
            </div>
            @if ($website->copilot_build_task_url)
                <a href="{{ $website->copilot_build_task_url }}" target="_blank" rel="noopener" class="shrink-0 rounded-md bg-violet-700 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-800">Open build task</a>
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
        <section class="@container rounded-xl border border-slate-950/10 bg-white" aria-labelledby="health-title">
        <div class="flex flex-col gap-4 border-b border-slate-950/10 p-5 @2xl:flex-row @2xl:items-start @2xl:justify-between sm:p-6">
            <div>
                <p class="font-mono text-sm text-teal-700">Website monitoring</p>
                <h2 id="health-title" class="mt-1 text-lg font-semibold text-slate-950">Health reports</h2>
                <p class="mt-1 text-base text-slate-600 sm:text-sm">Availability, on-page SEO, security headers, discoverability, and form delivery.</p>
            </div>
            @if ($canManageWebsite && $canRunHealthReports)
                <form method="POST" action="{{ route('admin.website-health-reports.store', $website) }}">
                    @csrf
                    <button type="submit" @class(['rounded-lg px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600', 'border border-slate-950/15 text-slate-700 hover:bg-slate-50' => $latestReport, 'bg-teal-600 text-white hover:bg-teal-700' => ! $latestReport])>Run report now</button>
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
                <label class="block min-w-0 @lg:min-w-80">
                    <span class="block text-base font-medium text-slate-700 sm:text-sm">Previous reports</span>
                    <select data-health-report-selector class="mt-1 block w-full rounded-lg border border-slate-950/15 bg-white px-3 py-2 text-base text-slate-900 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20 sm:text-sm" aria-label="Select a website health report">
                        @foreach ($website->healthReports as $historicalReport)
                            <option value="{{ route('admin.website-health-reports.show', [$website, $historicalReport]) }}">{{ $historicalReport->created_at->format('j M Y, H:i') }} · {{ ucfirst(str_replace('_', ' ', $historicalReport->overall_status ?: $historicalReport->status)) }}{{ $loop->first ? ' · Latest' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <a href="{{ route('admin.website-health-reports.show', [$website, $latestReport]) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-teal-600 px-3 py-2 text-sm font-medium text-white hover:bg-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">View latest report</a>
            </div>
        @else
            <p class="p-5 text-base text-slate-600 sm:p-6 sm:text-sm">No health reports have been generated yet.</p>
        @endif
        </section>

    </div>

    @if ($canUseSearchConsole)
    <div id="website-panel-search" class="space-y-6" role="region" aria-labelledby="website-tab-search" data-tab-panel="search" @if ($currentWebsiteSection !== 'search') hidden @endif>
        <section class="rounded-lg border bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Content intelligence</p>
                    <h2 class="mt-1 font-semibold">Google Search Console</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $website->searchConsoleConnection?->property_url ?: 'Connect a property to use real search queries, clicks, impressions, and rankings.' }}</p>
                </div>
                @if ($website->searchConsoleConnection)
                    <form method="POST" action="{{ route('admin.search-console.destroy', $website) }}">@csrf @method('DELETE')<button class="rounded-md border px-3 py-2 text-sm font-medium text-slate-700">Disconnect</button></form>
                @else
                    <a href="{{ route('admin.search-console.connect', $website) }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white">Connect Google</a>
                @endif
            </div>
            @if ($searchConsoleReport)
                <div class="mt-5 border-t border-slate-200 pt-5">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <h3 class="font-medium text-slate-900">Search performance</h3>
                        <div class="flex items-center gap-3 text-xs">
                            <p class="text-slate-500">{{ \Illuminate\Support\Carbon::parse($searchConsoleReport['period']['start'])->format('j M') }}–{{ \Illuminate\Support\Carbon::parse($searchConsoleReport['period']['end'])->format('j M Y') }}</p>
                            <a href="{{ route('admin.search-console.performance', $website) }}" class="font-medium text-slate-900 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-900">View all data</a>
                        </div>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Clicks</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['clicks']) }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Impressions</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['impressions']) }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Average CTR</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['ctr'] * 100, 1) }}%</dd></div>
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Average position</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['position'], 1) }}</dd></div>
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
                <p class="mt-4 border-t border-slate-200 pt-4 text-sm text-amber-700">Search performance is temporarily unavailable. The rest of the dashboard is unaffected.</p>
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
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><div class="h-2 w-16 rounded-full bg-violet-100"></div><h3 class="mt-4 font-semibold text-slate-900">{{ $feature }}</h3><p class="mt-1 text-sm text-slate-500">Available with an active Sitewell plan.</p></div>
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
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"><span class="text-xs font-semibold uppercase tracking-widest text-violet-700">SEO Intelligence</span><h3 class="mt-2 font-semibold text-slate-950">{{ $feature }}</h3><p class="mt-2 text-sm text-slate-600">{{ $description }}</p></article>
            @endforeach
        </div>
    </div>
    @endif

    <div id="website-panel-content" class="space-y-6" role="region" aria-labelledby="website-tab-content" data-tab-panel="content" @if ($currentWebsiteSection !== 'content') hidden @endif>
        @unless ($canUseGrowthFeatures)
            <x-feature-upgrade-banner tier="Growth" title="Plan and request new content" description="Upgrade to Growth to submit content requests, plan improvements, and prepare reviewable website changes." />
        @endunless

        @if ($canUseGrowthFeatures && ! $hasContentDeliveryConnection)
            <section class="overflow-hidden rounded-xl border border-violet-200 bg-white shadow-sm" aria-labelledby="content-connection-title">
                <div class="bg-violet-50 px-5 py-6 sm:px-6">
                    <p class="text-xs font-semibold uppercase tracking-widest text-violet-700">Connect your website</p>
                    <h2 id="content-connection-title" class="mt-2 text-xl font-semibold tracking-tight text-slate-950">Choose how Sitewell prepares website changes</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Your Sitewell package includes three ways to connect your website. A Sitewell specialist will help you choose the safest option for your setup and get it connected.</p>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-3 sm:p-6">
                    <article class="rounded-lg border border-slate-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Option 1</p>
                        <h3 class="mt-2 font-semibold text-slate-950">Sitewell Pixel</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">A lightweight connection for supported page updates without replacing your website platform.</p>
                    </article>
                    <article class="rounded-lg border border-slate-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Option 2</p>
                        <h3 class="mt-2 font-semibold text-slate-950">WordPress</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Connect your WordPress website so our specialists can prepare and manage compatible changes.</p>
                    </article>
                    <article class="rounded-lg border border-slate-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Option 3</p>
                        <h3 class="mt-2 font-semibold text-slate-950">GitHub</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Link the website repository for larger content and code changes delivered through a reviewable workflow.</p>
                    </article>
                </div>
                <div class="flex flex-col gap-4 border-t border-slate-200 bg-slate-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-950">Not sure which connection suits your website?</h3>
                        <p class="mt-1 text-sm text-slate-600">Book a free call with support to discuss the options and arrange the setup.</p>
                    </div>
                    <a href="{{ $contentSupportCallUrl }}" target="_blank" rel="noreferrer" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">Book a call with support <span class="ml-2" aria-hidden="true">→</span></a>
                </div>
            </section>
        @endif

        @if (Auth::user()?->isAdmin())
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Content publishing</p>
                    <h2 class="mt-1 font-semibold">GitHub repository</h2>
                    @if ($website->repository)
                        <p class="mt-1 text-sm text-slate-600">
                            <span class="font-medium text-slate-900">{{ $website->repository->full_name }}</span>
                            on {{ $website->repository->default_branch }}
                            @if ($website->repository->project_path)
                                · {{ $website->repository->project_path }}
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Installed for {{ $website->repository->installation->account_login }}.</p>
                    @else
                        <p class="mt-1 text-sm text-slate-600">Connect the website's source repository before adding requests or generating content.</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.website-repositories.create', $website) }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">{{ $website->repository ? 'Change repository' : 'Connect GitHub' }}</a>
                    @if ($website->repository)
                        <form method="POST" action="{{ route('admin.website-repositories.destroy', $website) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Disconnect</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
        @endif

        @if ($website->repository || ($canUseGrowthFeatures && config('forms.pixel_ui_enabled') && $website->pixel_enabled))
        @if ($canUseGrowthFeatures && $canManageWebsite)
        @php
            $contentPlan = $website->contentPlan;
        @endphp
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><p class="text-xs font-medium uppercase tracking-wide text-slate-500">AI content</p><h2 class="mt-1 font-semibold">Content schedule</h2><p class="mt-1 text-sm text-slate-600">Sitewell prioritises your queued requests, then eligible target keywords. Changes are prepared for review before publishing; runs with no useful work are skipped.</p></div>
                @if ($website->repository && Auth::user()?->isAdmin())
                    <form method="POST" action="{{ route('admin.content-generations.store', $website) }}">@csrf<button class="rounded-md border px-3 py-2 text-sm font-medium text-slate-700">Generate now</button></form>
                @endif
            </div>
            @if ($errors->has('enabled'))<p class="mt-3 text-sm text-red-700">{{ $errors->first('enabled') }}</p>@endif
            @if ($nextContentRun)
                <p class="mt-3 text-sm text-slate-600">Next scheduled run: {{ $nextContentRun->setTimezone($contentPlan->timezone)->format('l j F, H:i') }} ({{ $contentPlan->timezone }}).</p>
            @elseif ($contentScheduleReason)
                <p class="mt-3 text-sm text-amber-800">{{ $contentScheduleReason }}</p>
            @endif
            <p class="mt-2 text-sm text-slate-500">Growth includes one scheduled run per week. Complete includes up to three. Selected days share the same time and timezone. Automatic targets rest for at least 14 days between improvements.</p>
            <form method="POST" action="{{ route('admin.content-plans.update', $website) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf @method('PUT')
                <label class="flex items-center gap-2 md:col-span-2"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $contentPlan?->enabled))><span class="text-sm font-medium">Enable scheduled content improvements</span></label>
                <div><label class="block text-sm font-medium" for="weekday">Primary day</label><select id="weekday" name="weekday" class="mt-1 w-full rounded-md border px-3 py-2 text-sm">@foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $value => $day)<option value="{{ $value }}" @selected((int) old('weekday', $contentPlan?->weekday ?? 1) === $value)>{{ $day }}</option>@endforeach</select></div>
                @if ($contentWeeklyLimit === 3)
                    <fieldset class="md:col-span-2">
                        <legend class="text-sm font-medium">Extra days (choose up to two, different from your primary day)</legend>
                        <div class="mt-2 flex flex-wrap gap-3">
                            @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $value => $day)
                                <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="additional_weekdays[]" value="{{ $value }}" @checked(in_array($value, old('additional_weekdays', session()->hasOldInput() ? [] : ($contentPlan?->additional_weekdays ?? []))))>{{ $day }}</label>
                            @endforeach
                        </div>
                        @error('additional_weekdays')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        @foreach ($errors->get('additional_weekdays.*') as $messages)
                            @foreach ($messages as $message)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@endforeach
                        @endforeach
                    </fieldset>
                @elseif ($contentPlan?->additional_weekdays)
                    <p class="text-sm text-amber-800 md:col-span-2">Your saved extra days are paused. They resume when this website has an active Complete subscription.</p>
                @endif
                <div><label class="block text-sm font-medium" for="hour">Hour</label><select id="hour" name="hour" class="mt-1 w-full rounded-md border px-3 py-2 text-sm">@for ($hour = 0; $hour < 24; $hour++)<option value="{{ $hour }}" @selected((int) old('hour', $contentPlan?->hour ?? 8) === $hour)>{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00</option>@endfor</select></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium" for="timezone">Timezone</label><input id="timezone" name="timezone" value="{{ old('timezone', $contentPlan?->timezone ?? 'Europe/London') }}" class="mt-1 w-full rounded-md border px-3 py-2 text-sm"></div>
                <div>
                    <label class="block text-sm font-medium" for="audience">Audience</label>
                    <textarea id="audience" name="audience" rows="8" maxlength="20000" class="mt-1 w-full rounded-md border px-3 py-2 text-sm">{{ old('audience', $contentPlan?->audience) }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Up to 20,000 characters.</p>
                    @error('audience')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium" for="guidance">Editorial guidance</label>
                    <textarea id="guidance" name="guidance" rows="8" maxlength="20000" class="mt-1 w-full rounded-md border px-3 py-2 text-sm">{{ old('guidance', $contentPlan?->guidance) }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Up to 20,000 characters.</p>
                    @error('guidance')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2"><button class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white">Save content plan</button></div>
            </form>
            @if ($contentPlan?->generations->isNotEmpty())
                <div class="mt-5 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2">Date</th><th>Status</th><th>Pull request</th><th class="text-right">Actions</th></tr></thead><tbody>@foreach ($contentPlan->generations as $generation)<tr class="border-b"><td class="py-2">{{ $generation->scheduled_for->toFormattedDateString() }}</td><td>{{ str_replace('_', ' ', $generation->status) }}@if ($generation->skip_reason)<p class="mt-1 max-w-sm text-xs text-slate-500">{{ $generation->skip_reason }}</p>@endif</td><td>@if ($generation->pull_request_url)<a class="font-medium underline" href="{{ $generation->pull_request_url }}">#{{ $generation->pull_request_number }}</a>@else — @endif</td><td><div class="flex justify-end gap-2">@if (Auth::user()?->isAdmin() && $generation->pull_request_number && $generation->status === \App\Models\ContentGeneration::STATUS_PULL_REQUEST_OPEN)<form method="POST" action="{{ route('admin.content-generations.sync', [$website, $generation]) }}">@csrf<button class="rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Check GitHub status</button></form><form method="POST" action="{{ route('admin.content-generations.destroy', [$website, $generation]) }}">@csrf @method('DELETE')<button class="rounded-md border border-red-200 bg-white px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Cancel</button></form>@else — @endif</div></td></tr>@endforeach</tbody></table></div>
            @endif
        </div>
        @endif

        @if ($canUseGrowthFeatures)
        <section class="rounded-lg border bg-white p-4 shadow-sm" aria-labelledby="content-requests-title">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Content ideas</p>
            <h2 id="content-requests-title" class="mt-1 font-semibold">Manual content requests</h2>
            <p class="mt-1 text-sm text-slate-600">Suggest a landing page, blog post, or other useful content change. Eligible changes to existing page titles and descriptions can be prepared as Pixel drafts; larger changes remain available to the GitHub content workflow.</p>
        </div>

        <form method="POST" action="{{ route('admin.content-requests.store', $website) }}" class="mt-4">
            @csrf
            <label class="block text-sm font-medium text-slate-900" for="content-request-instructions">What would you like Sitewell to create or change?</label>
            <textarea id="content-request-instructions" name="instructions" rows="5" maxlength="3000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="For example: Create a new landing page or blog post for a specific category.">{{ old('instructions') }}</textarea>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs text-slate-500">Include the audience, useful keywords, desired location in the site, and any claims or qualifications that must be preserved. Up to 3,000 characters.</p>
                    @error('instructions')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Add content request</button>
            </div>
        </form>

        <div class="mt-5 border-t border-slate-200 pt-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-slate-900">Pending todos</h3>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium tabular-nums text-amber-800">{{ $pendingContentRequests->total() }}</span>
            </div>
            <div class="mt-3 space-y-3">
                @forelse ($pendingContentRequests as $contentRequest)
                    @php
                        $queuePosition = $pendingContentRequests->firstItem() + $loop->index;
                    @endphp
                    <article class="rounded-lg border border-slate-200 p-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($queuePosition === 1)
                                        <span class="rounded-full bg-teal-100 px-2.5 py-1 text-xs font-medium text-teal-800">Up next</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium tabular-nums text-amber-800">Queue #{{ $queuePosition }}</span>
                                    @endif
                                    @if ($contentRequest->bumped_at)
                                        <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-medium text-violet-800">Bumped</span>
                                    @endif
                                    <span class="text-xs text-slate-500">Added {{ $contentRequest->created_at->diffForHumans() }}{{ $contentRequest->creator ? ' by '.$contentRequest->creator->name : '' }}</span>
                                </div>
                                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $contentRequest->instructions }}</p>
                            </div>
                            @if ($canManageWebsite)
                                <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                                    @if ($queuePosition !== 1)
                                        <form method="POST" action="{{ route('admin.content-requests.bump', [$website, $contentRequest]) }}">
                                            @csrf
                                            <button type="submit" class="min-h-11 w-full rounded-md border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-medium text-violet-800 hover:bg-violet-100 sm:w-auto">Bump to top</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.content-requests.destroy', [$website, $contentRequest]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="min-h-11 w-full rounded-md border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 sm:w-auto">Remove</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">No pending content todos.</p>
                @endforelse
            </div>
            @if ($pendingContentRequests->hasPages())
                <div class="mt-4">{{ $pendingContentRequests->links() }}</div>
            @endif
        </div>

        @if ($actionedContentRequests->isNotEmpty())
            <div class="mt-5 border-t border-slate-200 pt-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Actioned todos</h3>
                        <p class="mt-1 text-xs text-slate-500">Requests already being prepared remain here as a permanent activity record.</p>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium tabular-nums text-emerald-800">{{ $actionedContentRequests->count() }}</span>
                </div>
                <div class="mt-3 space-y-3">
                    @foreach ($actionedContentRequests as $contentRequest)
                        <article class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">Actioned</span>
                                        @if ($contentRequest->generation)
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium capitalize text-slate-700">{{ str_replace('_', ' ', $contentRequest->generation->status) }}</span>
                                        @endif
                                        <span class="text-xs text-slate-500">Picked up {{ $contentRequest->picked_up_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $contentRequest->instructions }}</p>
                                </div>
                                @if ($contentRequest->generation?->pull_request_url)
                                    <a href="{{ $contentRequest->generation->pull_request_url }}" target="_blank" rel="noreferrer" class="shrink-0 rounded-md border border-emerald-300 bg-white px-2.5 py-1.5 text-xs font-medium text-emerald-800 hover:bg-emerald-100">View pull request</a>
                                @elseif ($contentRequest->generation?->copilot_task_url)
                                    <a href="{{ $contentRequest->generation->copilot_task_url }}" target="_blank" rel="noreferrer" class="shrink-0 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View generation task</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif
        </section>
        @else
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="content-preview-title">
            <p class="text-xs font-semibold uppercase tracking-widest text-violet-700">Content workflow</p>
            <h2 id="content-preview-title" class="mt-2 font-semibold text-slate-950">From an idea to a reviewable website change</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-slate-50 p-4"><span class="text-sm font-semibold text-violet-700">01</span><h3 class="mt-2 text-sm font-semibold text-slate-900">Request content</h3><p class="mt-1 text-sm text-slate-600">Describe a landing page, article, or improvement you need.</p></div>
                <div class="rounded-lg bg-slate-50 p-4"><span class="text-sm font-semibold text-violet-700">02</span><h3 class="mt-2 text-sm font-semibold text-slate-900">Sitewell prepares it</h3><p class="mt-1 text-sm text-slate-600">Content is created in your repository against your site and audience.</p></div>
                <div class="rounded-lg bg-slate-50 p-4"><span class="text-sm font-semibold text-violet-700">03</span><h3 class="mt-2 text-sm font-semibold text-slate-900">Review before publishing</h3><p class="mt-1 text-sm text-slate-600">Nothing goes live until the proposed change has been reviewed.</p></div>
            </div>
        </section>
        @endif
        @endif
    </div>

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
                    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"><span class="text-xs font-semibold uppercase tracking-widest text-violet-700">Google Business Profile</span><h3 class="mt-2 font-semibold text-slate-950">{{ $feature }}</h3><p class="mt-2 text-sm text-slate-600">{{ $description }}</p></article>
                @endforeach
            </div>
        </div>
    @endif

    <div id="website-panel-settings" class="space-y-6" role="region" aria-labelledby="website-tab-settings" data-tab-panel="settings" @if ($currentWebsiteSection !== 'settings') hidden @endif>
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="website-settings-title">
            <div class="border-b border-slate-200 p-5 sm:p-6">
                <p class="font-mono text-sm text-teal-700">Website settings</p>
                <h2 id="website-settings-title" class="mt-1 text-xl font-semibold text-slate-950">Your website details</h2>
                <p class="mt-1 text-base text-slate-600 sm:text-sm">Choose the name Sitewell uses for this website throughout your account.</p>
            </div>

            @if ($canManageWebsite)
                <form method="POST" action="{{ route('admin.websites.update', $website) }}" class="flex flex-col gap-4 p-5 sm:p-6">
                    @csrf
                    @method('PUT')
                    <div class="max-w-xl">
                        <label class="text-base font-medium text-slate-700 sm:text-sm" for="name">Website name</label>
                        <input id="name" name="name" type="text" required value="{{ old('name', $website->name) }}" class="mt-1 w-full rounded-lg border border-slate-950/15 bg-white px-3 py-2 text-base text-slate-950 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20 sm:text-sm">
                        @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div><button type="submit" class="rounded-lg bg-teal-600 px-3 py-2 text-sm font-medium text-white hover:bg-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Save name</button></div>
                </form>
            @endif

            @if (Auth::user()?->isAdmin())
                <details class="border-t border-slate-950/10">
                    <summary class="cursor-pointer px-5 py-4 text-base font-medium text-slate-800 hover:bg-slate-50 sm:px-6 sm:text-sm">Advanced website settings</summary>
                    <form method="POST" action="{{ route('admin.websites.update', $website) }}" class="space-y-6 border-t border-slate-950/10 p-5 sm:p-6">
                        @csrf
                        @method('PUT')
                    <fieldset class="space-y-3 border-t border-slate-200 pt-6">
                        <legend class="text-sm font-semibold text-slate-950">Connection workspaces</legend>
                        <p class="text-sm text-slate-600">Choose which connection tabs are available for this website.</p>
                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4">
                            <input type="hidden" name="wordpress_enabled" value="0">
                            <input type="checkbox" name="wordpress_enabled" value="1" class="mt-1 rounded border-slate-300" @checked(old('wordpress_enabled', $website->wordpress_enabled))>
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Enable the WordPress connection</span>
                                <span class="block text-xs leading-5 text-slate-500">Shows the WordPress tab for pairing the plugin and managing website releases.</span>
                            </span>
                        </label>
                        @if (config('forms.pixel_ui_enabled'))
                            <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4">
                                <input type="hidden" name="pixel_enabled" value="0">
                                <input type="checkbox" name="pixel_enabled" value="1" class="mt-1 rounded border-slate-300" @checked(old('pixel_enabled', $website->pixel_enabled))>
                                <span>
                                    <span class="block text-sm font-medium text-slate-900">Enable the Pixel connection</span>
                                    <span class="block text-xs leading-5 text-slate-500">Shows the Pixel tab and allows approved Pixel changes to be delivered to the website.</span>
                                </span>
                            </label>
                        @endif
                    </fieldset>
                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3">
                        <input type="hidden" name="health_reports_enabled" value="0">
                        <input type="checkbox" name="health_reports_enabled" value="1" class="mt-1 rounded border-slate-300" @checked($website->health_reports_enabled)>
                        <span>
                            <span class="block text-sm font-medium text-slate-900">Send weekly website health reports</span>
                            <span class="block text-xs text-slate-500">Reports are emailed to administrators and assigned website users.</span>
                        </span>
                    </label>
                    <div class="space-y-3 rounded-lg border border-slate-200 p-3">
                        <label class="flex items-start gap-3">
                            <input type="hidden" name="webhook_enabled" value="0">
                            <input type="checkbox" name="webhook_enabled" value="1" class="mt-1 rounded border-slate-300" @checked(old('webhook_enabled', $website->webhook_enabled))>
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Send submissions to a webhook</span>
                                <span class="block text-xs text-slate-500">New form submissions will be posted to the URL below unless a form has its own override.</span>
                            </span>
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="webhook_url">Webhook URL</label>
                            <input id="webhook_url" name="webhook_url" type="url" value="{{ old('webhook_url', $website->webhook_url) }}" placeholder="https://example.com/webhooks/forms" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('webhook_url')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="webhook_secret">Webhook secret</label>
                            <input id="webhook_secret" name="webhook_secret" type="text" value="{{ old('webhook_secret', $website->webhook_secret) }}" autocomplete="off" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <p class="mt-1 text-xs text-slate-500">Optional secret used to sign webhook requests.</p>
                            @error('webhook_secret')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="space-y-3 rounded-lg border border-slate-200 p-3">
                        <label class="flex items-start gap-3">
                            <input type="hidden" name="turnstile_enabled" value="0">
                            <input type="checkbox" name="turnstile_enabled" value="1" class="mt-1 rounded border-slate-300" @checked(old('turnstile_enabled', $website->turnstile_enabled))>
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Protect submissions with Cloudflare Turnstile</span>
                                <span class="block text-xs text-slate-500">Submissions that fail verification are silently quarantined as spam.</span>
                            </span>
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="turnstile_site_key">Turnstile site key</label>
                            <input id="turnstile_site_key" name="turnstile_site_key" type="text" value="{{ old('turnstile_site_key', $website->turnstile_site_key) }}" autocomplete="off" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm">
                            @error('turnstile_site_key')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="turnstile_secret_key">Turnstile secret key</label>
                            <input id="turnstile_secret_key" name="turnstile_secret_key" type="password" value="" placeholder="{{ $website->turnstile_secret_key ? 'Configured — leave blank to keep it' : '' }}" autocomplete="new-password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm">
                            <p class="mt-1 text-xs text-slate-500">Stored server-side and never included in the website form.</p>
                            @error('turnstile_secret_key')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                        <button type="submit" class="rounded-lg border border-slate-950/15 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Save advanced settings</button>
                    </form>
                </details>

                <div class="border-t border-red-200 px-5 py-5 sm:px-6">
                    <h3 class="text-sm font-semibold text-red-900">Delete website</h3>
                    <p class="mt-1 text-sm text-red-700">This permanently deletes the website, its forms, submissions, reports, and content settings.</p>
                    <form method="POST" action="{{ route('admin.websites.destroy', $website) }}" class="mt-3" onsubmit="return confirm('Delete this website and all of its data? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Delete website</button>
                    </form>
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold">Website users</h2>
                    <p class="mt-1 text-sm text-slate-600">Managers can make changes. Viewers have read-only access.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-700">{{ $websiteUsers->count() }} {{ Str::plural('user', $websiteUsers->count()) }}</span>
            </div>

            @if (Auth::user()?->isAdmin())
                <form method="POST" action="{{ route('admin.websites.update', $website) }}" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    @csrf
                    @method('PUT')
                    <label for="subscription_user_id" class="block text-sm font-medium text-slate-700">Subscription account</label>
                    <p class="mt-1 text-sm text-slate-600">This member’s package unlocks the website’s features. Their Viewer or Manager access stays unchanged. Your administrator access lets you manage the website for them.</p>
                    <select id="subscription_user_id" name="subscription_user_id" class="mt-3 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">No subscription account</option>
                        @foreach ($websiteUsers as $websiteUser)
                            <option value="{{ $websiteUser['user']->id }}" @selected((string) old('subscription_user_id', $website->user_id) === (string) $websiteUser['user']->id)>{{ $websiteUser['user']->name }} — {{ $websiteUser['user']->email }}</option>
                        @endforeach
                    </select>
                    @error('subscription_user_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    <button type="submit" class="mt-3 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Save subscription account</button>
                </form>
            @endif

            @error('role')
                <p class="mt-4 rounded-lg bg-red-50 p-3 text-base text-red-700 sm:text-sm" role="alert">{{ $message }}</p>
            @enderror

            <div class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-200">
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
                                <details class="mt-2 rounded-lg border border-slate-200 p-3">
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
                                        <button type="submit" class="mt-3 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Save membership</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                        @if ($canManageMembers && ! $isOnlyManager)
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('admin.websites.members.update', [$website, $member]) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <label for="member_role_{{ $member->id }}" class="sr-only">Access for {{ $member->name }}</label>
                                    <select id="member_role_{{ $member->id }}" name="role" class="rounded-lg border border-slate-950/15 bg-white px-2 py-1.5 text-base sm:text-sm">
                                        <option value="manager" @selected($memberRole === 'manager')>Manager</option>
                                        <option value="viewer" @selected($memberRole === 'viewer')>Viewer</option>
                                    </select>
                                    <button type="submit" class="rounded-lg border border-slate-950/15 px-2.5 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Update</button>
                                </form>
                                <form method="POST" action="{{ route('admin.websites.members.destroy', [$website, $member]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">Remove</button>
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
                    <div><label for="member_email" class="text-sm font-medium text-slate-700">Invite by email</label><input id="member_email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" placeholder="colleague@example.com" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><p class="mt-1 text-xs text-slate-500">We’ll email them a secure link to set up their account.</p>@error('email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    <div><label for="member_role" class="text-sm font-medium text-slate-700">Access</label><select id="member_role" name="role" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="manager" @selected(old('role', 'viewer') === 'manager')>Manager</option><option value="viewer" @selected(old('role', 'viewer') === 'viewer')>Viewer</option></select></div>
                    @if (Auth::user()?->isAdmin())
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 sm:col-span-3">
                            @include('admin.websites.partials.member-membership-fields', [
                                'membershipFormKey' => 'invite',
                                'membershipTier' => null,
                                'membershipEndsOn' => now()->addMonthsNoOverflow(6)->format('Y-m-d'),
                                'membershipRequired' => false,
                            ])
                        </div>
                    @endif
                    <button type="submit" class="rounded-md border border-slate-950/15 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Send invitation</button>
                </form>
            @elseif ($canManageMembers)
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">A Growth or Complete membership is required to invite additional website users.</p>
            @endif
        </section>
    </div>

    <div id="website-panel-forms" class="grid gap-6 lg:grid-cols-2" role="region" aria-labelledby="website-tab-forms" data-tab-panel="forms" @if ($currentWebsiteSection !== 'forms') hidden @endif>
        <section class="@container rounded-xl border border-slate-950/10 bg-white p-5 lg:col-span-2 sm:p-6" aria-labelledby="form-onboarding-title">
            <h2 id="form-onboarding-title" class="text-xl font-semibold text-slate-950">Connect a website form</h2>
            <p class="mt-1 text-base text-slate-600 sm:text-sm">Use the installation example when adding a new form to this website.</p>
            <details class="group mt-4 rounded-lg bg-slate-50 open:ring-1 open:ring-slate-950/10">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-4 py-3 text-base font-medium text-slate-800 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 sm:text-sm [&::-webkit-details-marker]:hidden">
                    Show installation instructions
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" class="size-5 shrink-0 group-open:rotate-180 sm:size-4" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </summary>
            <div class="grid gap-5 border-t border-slate-950/10 p-4 @4xl:grid-cols-[2fr_3fr] @4xl:gap-6">
                <div class="min-w-0">
                    <p class="mt-2 text-base text-pretty text-blue-900 sm:text-sm">Paste the example into the website, then replace or add the fields you need. Submissions from {{ $website->domains->firstWhere('is_primary', true)?->domain ?? $website->domains->first()?->domain ?? 'this website' }} will be matched automatically.</p>

                    <dl class="mt-5 grid gap-4">
                        <div>
                            <dt class="text-base font-semibold text-blue-950 sm:text-sm">1. Post to the shared endpoint</dt>
                            <dd class="mt-1 text-base text-pretty text-blue-900 sm:text-sm">Use <code class="font-mono">POST</code> and the action shown in the example. No API key, website ID, or CSRF field is required.</dd>
                        </div>
                        <div>
                            <dt class="text-base font-semibold text-blue-950 sm:text-sm">2. Give the form a stable name</dt>
                            <dd class="mt-1 text-base text-pretty text-blue-900 sm:text-sm">Set <code class="font-mono">_form_name</code> to a clear name such as “Contact form”. Keep it unchanged after launch; a different name may create a separate form.</dd>
                        </div>
                        <div>
                            <dt class="text-base font-semibold text-blue-950 sm:text-sm">3. Keep the honeypot empty</dt>
                            <dd class="mt-1 text-base text-pretty text-blue-900 sm:text-sm">Include the hidden <code class="font-mono">_honeypot</code> field. Visitors will not see it, while bots that fill it will be recorded as spam without triggering notifications.</dd>
                        </div>
                    </dl>
                </div>

                <div class="min-w-0 rounded-md bg-slate-950 p-3 [--padding:--spacing(3)] [--radius:var(--radius-md)]">
                    <div class="flex items-center justify-between gap-3 pb-3">
                        <p class="min-w-0 truncate font-mono text-sm text-slate-300">HTML · complete example</p>
                        <button type="button" class="js-copy-text relative shrink-0 rounded-md border border-white/15 bg-white/10 px-3 py-2 text-base font-medium text-white hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:text-sm" data-copy-target="form-onboarding-example" data-copy-label="Copy example" data-copied-label="Copied">Copy example<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></button>
                    </div>
                    <textarea id="form-onboarding-example" class="h-96 w-full resize-y rounded-[calc(var(--radius)-var(--padding))] border-0 bg-slate-900 p-3 font-mono text-sm text-slate-100 focus:outline-2 focus:outline-offset-2 focus:outline-blue-400" readonly spellcheck="false">&lt;form method="POST" action="{{ route('forms.submit') }}"&gt;
    &lt;input type="hidden" name="_form_name" value="Contact form"&gt;

    &lt;div
        style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"
        aria-hidden="true"
    &gt;
        &lt;label&gt;
            Leave this field empty
            &lt;input
                type="text"
                name="_honeypot"
                tabindex="-1"
                autocomplete="off"
            &gt;
        &lt;/label&gt;
    &lt;/div&gt;

    &lt;label&gt;
        Name
        &lt;input type="text" name="name" required&gt;
    &lt;/label&gt;

    &lt;label&gt;
        Email
        &lt;input type="email" name="email" required&gt;
    &lt;/label&gt;

    &lt;label&gt;
        Message
        &lt;textarea name="message" required&gt;&lt;/textarea&gt;
    &lt;/label&gt;

    &lt;!-- Replace YOUR_TURNSTILE_SITE_KEY with your Cloudflare public site key.
         Save the matching site and secret keys in Sitewell website settings
         and enable Turnstile protection. Never put the secret key here.
         The widget adds the cf-turnstile-response field automatically. --&gt;
    &lt;div class="cf-turnstile" data-sitekey="{{ $website->turnstile_site_key ?: 'YOUR_TURNSTILE_SITE_KEY' }}"&gt;&lt;/div&gt;

    &lt;button type="submit"&gt;Send enquiry&lt;/button&gt;
&lt;/form&gt;

&lt;!-- Include this script once per page. --&gt;
&lt;script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer&gt;&lt;/script&gt;</textarea>
                </div>
            </div>
            </details>
        </section>

        <div class="rounded-xl border border-slate-950/10 bg-white p-5 lg:col-span-2 sm:p-6">
            <h2 class="font-semibold text-blue-950">Automatic customer reply</h2>
            @if (! $canUseAutoresponders)
                <p class="mt-1 text-base text-slate-600 sm:text-sm">Automatic customer replies require an active Sitewell plan.</p>
                <a href="{{ route('admin.billing.index') }}" class="mt-4 inline-flex items-center justify-center rounded-lg border border-slate-950/15 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">View plans</a>
            @else
            <p class="mt-1 text-base text-blue-800 sm:text-sm">Set the website-wide acknowledgement. Individual forms can inherit or override it.</p>
            <details class="group mt-4 rounded-lg border border-slate-950/10">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-4 py-3 text-base font-medium text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 sm:text-sm [&::-webkit-details-marker]:hidden">Edit automatic reply<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" class="size-5 shrink-0 group-open:rotate-180 sm:size-4" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
            <form method="POST" action="{{ route('admin.websites.autoresponder.update', $website) }}" class="space-y-4 border-t border-slate-950/10 p-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="autoresponder_enabled" value="0">
                <label class="flex items-start gap-3 rounded-lg border border-blue-200 bg-white p-3">
                    <input type="checkbox" name="autoresponder_enabled" value="1" class="mt-1 rounded border-slate-300" @checked(old('autoresponder_enabled', $website->autoresponder_enabled))>
                    <span><span class="block text-sm font-medium text-slate-900">Automatically acknowledge new enquiries</span><span class="block text-xs text-slate-500">Only sends when a valid customer email is present and the submission passes spam checks.</span></span>
                </label>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="autoresponder_from_name">From name</label>
                        <input id="autoresponder_from_name" name="autoresponder_from_name" value="{{ old('autoresponder_from_name', $website->autoresponder_from_name) }}" placeholder="{{ config('mail.from.name') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('autoresponder_from_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div><p class="text-base font-medium text-slate-700 sm:text-sm">From email address</p><p class="mt-1 text-base text-slate-600 sm:text-sm">{{ config('forms.autoresponder_from_address') }}</p></div>
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="autoresponder_subject">Email subject</label>
                        <input id="autoresponder_subject" name="autoresponder_subject" value="{{ old('autoresponder_subject', $website->autoresponder_subject) }}" placeholder="We've received your {form_name} enquiry" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    @php($autoresponderContentType = old('autoresponder_content_type', $website->autoresponder_content_type ?? 'text'))
                    <div class="space-y-4 lg:row-span-2" data-autoresponder-content-editor data-field-name="autoresponder_body">
                        <div>
                            <label class="text-sm font-medium text-slate-700" for="autoresponder_content_type">Message format</label>
                            <select id="autoresponder_content_type" name="autoresponder_content_type" class="mt-1 w-full" data-autoresponder-content-type>
                                <option value="text" @selected($autoresponderContentType === 'text')>Text</option>
                                <option value="html" @selected($autoresponderContentType === 'html')>HTML</option>
                            </select>
                        </div>
                        <div data-autoresponder-content-panel="text" @if ($autoresponderContentType !== 'text') hidden @endif>
                            <label class="text-sm font-medium text-slate-700" for="autoresponder_body">Email message</label>
                            <x-trix-editor id="autoresponder_body" name="autoresponder_body" :value="old('autoresponder_body', $website->autoresponder_body)" placeholder="Write the automatic reply…" />
                        </div>
                        <div data-autoresponder-content-panel="html" @if ($autoresponderContentType !== 'html') hidden @endif>
                            <label class="text-sm font-medium text-slate-700" for="autoresponder_body_html">Raw HTML</label>
                            <textarea id="autoresponder_body_html" name="autoresponder_body" rows="10" class="mt-1 w-full font-mono text-sm" placeholder="<!doctype html>…">{{ old('autoresponder_body', $website->autoresponder_body) }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">HTML is sent exactly as entered. Use complete email-safe markup and inline styles where needed.</p>
                        </div>
                        @error('autoresponder_body')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-slate-500">Use any submitted field name as a tag, for example {email}, {phone}, or {budget}. Also available: {name}, {form_name}, {website_name}, {website_domain}, {submission_id}.</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="autoresponder_delay_minutes">Send delay (minutes)</label>
                        <input id="autoresponder_delay_minutes" type="number" min="0" max="10080" name="autoresponder_delay_minutes" value="{{ old('autoresponder_delay_minutes', $website->autoresponder_delay_minutes ?? 0) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Use 0 to queue the reply immediately.</p>
                    </div>
                    <div><button class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Save automatic reply</button></div>
                </div>
            </form>
            </details>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-950/10 bg-white lg:col-span-2">
            <table class="min-w-full divide-y divide-slate-950/10">
                <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Form</th><th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Status</th><th class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Submissions</th></tr></thead>
                <tbody class="divide-y divide-slate-950/5">
                @forelse ($website->forms as $form)
                    <tr class="hover:bg-slate-50"><td class="px-4 py-3"><a href="{{ route('admin.forms.show', $form) }}" class="font-medium text-slate-900 hover:text-teal-700">{{ $form->name }}</a></td><td class="px-4 py-3 text-sm text-slate-600">{{ $form->is_active ? 'Active' : 'Disabled' }}</td><td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ $form->submissions_count }}</td></tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-base text-slate-500 sm:text-sm">No forms registered for this website.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <details id="website-assistant" @if (request('assistant') === 'open' || $errors->has('question')) open @endif class="group fixed right-3 bottom-3 left-3 z-50 overflow-hidden rounded-2xl border border-slate-700 bg-white shadow-2xl open:border-slate-200 sm:right-6 sm:bottom-6 sm:left-auto sm:w-[26rem]">
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
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <p class="text-xs leading-5 text-slate-600">Restricted to this website’s stored reports and search data.</p>
                    <span class="shrink-0 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-teal-800">{{ max(0, $websiteAiWeeklyLimit - $websiteAiQuestionsUsed) }}/{{ $websiteAiWeeklyLimit }} left</span>
                </div>

                <div data-website-ai-messages class="min-h-24 flex-1 divide-y divide-slate-100 overflow-y-auto overscroll-contain">
                    @forelse ($websiteAiQuestions->reverse() as $websiteAiQuestion)
                        <article data-website-ai-question data-status-url="{{ route('admin.websites.assistant.questions.show', [$website, $websiteAiQuestion]) }}" data-status="{{ $websiteAiQuestion->status }}" class="space-y-2 px-4 py-3">
                            <div class="ml-8 rounded-2xl rounded-br-md bg-slate-100 px-3 py-2 text-sm text-slate-800">{{ $websiteAiQuestion->question }}</div>
                            @if ($websiteAiQuestion->status === 'completed')
                                <p data-website-ai-response class="mr-8 whitespace-pre-line rounded-2xl rounded-bl-md bg-teal-50 px-3 py-2 text-sm leading-6 text-slate-700">{{ $websiteAiQuestion->answer }}</p>
                            @elseif ($websiteAiQuestion->status === 'failed')
                                <p data-website-ai-response class="mr-8 rounded-2xl rounded-bl-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ $websiteAiQuestion->error }}</p>
                            @else
                                <p data-website-ai-response class="text-sm text-slate-500">Preparing an answer…</p>
                            @endif
                            @if (in_array($websiteAiQuestion->status, ['completed', 'failed'], true))
                                @if ($websiteAiQuestion->reported_at)
                                    <p class="px-1 text-right text-[11px] font-medium text-amber-700">Reported for investigation</p>
                                @else
                                    <details class="group/report px-1 text-right">
                                        <summary class="cursor-pointer list-none text-[11px] text-slate-500 hover:text-slate-800 [&::-webkit-details-marker]:hidden">This answer wasn’t helpful?</summary>
                                        <form method="POST" action="{{ route('admin.websites.assistant.questions.report', [$website, $websiteAiQuestion]) }}" class="mt-2 space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-left">
                                            @csrf
                                            <label for="website-assistant-report-{{ $websiteAiQuestion->id }}" class="block text-xs font-medium text-slate-700">What should it have understood? <span class="font-normal text-slate-500">Optional</span></label>
                                            <textarea id="website-assistant-report-{{ $websiteAiQuestion->id }}" name="reason" rows="2" maxlength="1000" placeholder="Tell us what you expected…" class="w-full resize-none rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs leading-5 text-slate-900 placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20"></textarea>
                                            <div class="flex items-center justify-between gap-3"><p class="text-[11px] text-slate-500">Our team will receive the question and response.</p><button type="submit" class="shrink-0 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">Report answer</button></div>
                                        </form>
                                    </details>
                                @endif
                            @endif
                        </article>
                    @empty
                        <div data-website-ai-empty class="space-y-3 p-5 text-center">
                            <div><p class="text-sm font-medium text-slate-900">What would you like to understand?</p><p class="mt-1 text-xs leading-5 text-slate-500">Ask about this website’s rankings, search performance, opportunities, or health reports.</p></div>
                            <div class="space-y-1.5 text-left text-xs text-slate-600">
                                <p class="font-semibold text-slate-700">Try asking:</p>
                                <p class="rounded-lg bg-slate-50 px-3 py-2">“Which keywords have improved or declined recently?”</p>
                                <p class="rounded-lg bg-slate-50 px-3 py-2">“What should we prioritise from the latest health report?”</p>
                                <p class="rounded-lg bg-slate-50 px-3 py-2">“How have clicks and impressions changed over the last six months?”</p>
                            </div>
                        </div>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('admin.websites.assistant.questions.store', $website) }}" data-website-ai-form class="space-y-2 border-t border-slate-200 p-3">
                    @csrf
                    <label for="website-assistant-question" class="sr-only">Ask a question about {{ $website->name }}</label>
                    <textarea id="website-assistant-question" name="question" rows="2" maxlength="1000" required placeholder="Ask about this website…" class="w-full resize-none rounded-xl border border-slate-300 px-3 py-2 text-sm leading-5 text-slate-900 placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20">{{ old('question') }}</textarea>
                    @error('question')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <p data-website-ai-error class="hidden text-xs text-red-600" role="alert"></p>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[11px] text-slate-500">Resets Monday · Out-of-scope requests count</p>
                        <button type="submit" @disabled($websiteAiQuestionsUsed >= $websiteAiWeeklyLimit) class="rounded-lg bg-teal-600 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:bg-slate-300">Send</button>
                    </div>
                </form>
            @else
                <div class="space-y-4 p-5">
                    <div><span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-800">Complete feature</span><h2 class="mt-3 font-semibold text-slate-950">Ask questions about your website data</h2><p class="mt-2 text-sm leading-6 text-slate-600">Upgrade to Complete to ask about health reports, Search Console performance, SEO rankings, and opportunities for this website.</p></div>
                    <a href="{{ route('admin.billing.index') }}" class="block rounded-lg bg-violet-700 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-violet-800">View packages</a>
                </div>
            @endif
        </div>
    </details>
</div>
@endsection
