@extends('layouts.app')

@section('content')
    @php
        $siteFilter = request('site_id');
        $hubUrl = fn ($section, $extra = []) => route('admin.overview', array_filter(['hub' => $section, 'site_id' => $siteFilter, ...$extra]));
    @endphp
    <div class="space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-5">
            <div class="min-w-0">
                <p class="font-mono text-base text-teal-700 sm:text-sm">Your website portfolio</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-balance text-slate-950 sm:text-3xl">Admin overview</h1>
                <p class="mt-2 text-base text-pretty text-slate-600 sm:text-sm">Choose the next improvement, review finished work, and keep every website moving.</p>
            </div>
            <form method="GET" action="{{ route('admin.overview') }}" class="flex w-full min-w-0 flex-wrap items-end gap-2 sm:w-auto">
                <input type="hidden" name="hub" value="{{ $hubSection }}">
                <input type="hidden" name="action_state" value="{{ $actionState }}">
                <div class="min-w-0 flex-1">
                    <label for="overview-site" class="ui-label">Website</label>
                    <select id="overview-site" name="site_id" class="ui-input mt-1 w-full sm:max-w-72">
                        <option value="">All websites · {{ $allWebsites->count() }}</option>
                        @foreach ($allWebsites as $site)
                            <option value="{{ $site->id }}" @selected((string) $siteFilter === (string) $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="ui-button ui-button-secondary">Apply</button>
                @if ($siteFilter)
                    <a href="{{ route('admin.overview', ['hub' => $hubSection]) }}" class="ui-button ui-button-secondary">Clear</a>
                @endif
            </form>
        </header>

        <div class="ui-panel ui-section @container">
            <dl class="grid grid-cols-2 gap-6 @3xl:grid-cols-4">
                @foreach ([['label' => 'Priorities to action', 'count' => $actionCounts->get('open', 0), 'section' => 'priorities'], ['label' => 'Awaiting approval', 'count' => $approvalCount, 'section' => 'approvals'], ['label' => 'SEO results ready', 'count' => $impactReviews->total(), 'section' => 'results'], ['label' => 'Websites', 'count' => $websites->count(), 'section' => 'websites']] as $stat)
                    <div class="min-w-0">
                        <dt class="truncate text-base font-medium text-slate-700 sm:text-sm">{{ $stat['label'] }}</dt>
                        <dd class="mt-2 text-3xl tabular-nums text-slate-600"><a href="{{ $hubUrl($stat['section']) }}" class="font-semibold text-slate-950 hover:text-teal-700">{{ number_format($stat['count']) }}</a></dd>
                    </div>
                @endforeach
            </dl>
            <div class="mt-5 flex flex-wrap justify-between gap-3 border-t border-slate-950/10 pt-4 text-base sm:text-sm">
                <p class="text-slate-600"><a href="{{ $hubUrl('automation') }}#content-queue" class="font-medium text-teal-700 hover:underline">Requests awaiting preparation · {{ number_format($websites->sum('pending_content_requests_count')) }}</a></p>
                @if ($workActivity['attention']->isNotEmpty())
                    <p class="text-amber-800"><a href="{{ $hubUrl('automation') }}#work-attention-heading" class="font-medium hover:underline">Recent work needs attention · {{ $workActivity['attention']->count() }}</a></p>
                @else
                    <p class="text-slate-500">No failed or stalled work found.</p>
                @endif
            </div>
        </div>

        <nav class="ui-tabs" aria-label="Admin overview sections">
            @foreach (['priorities' => 'Priorities', 'approvals' => 'Approvals', 'results' => 'SEO results', 'automation' => 'Automation', 'websites' => 'Websites'] as $section => $label)
                <a id="hub-tab-{{ $section }}" href="{{ $hubUrl($section) }}" class="ui-tab" @if ($hubSection === $section) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <section id="hub-priorities" aria-labelledby="hub-tab-priorities" @if ($hubSection !== 'priorities') hidden @endif>
            <x-admin-priority-actions :actions="$priorityActions" :counts="$actionCounts" :state="$actionState" :site-filter="$siteFilter" />
        </section>
        <section id="hub-approvals" class="ui-panel ui-section @container" aria-labelledby="hub-tab-approvals" @if ($hubSection !== 'approvals') hidden @endif>
            <h2 id="approvals-heading" class="text-xl font-semibold text-balance text-slate-950">Actions to review</h2>
            <p class="mt-2 text-base text-slate-600 sm:text-sm">Open the website work to inspect changes and approve them.</p>
            <div class="mt-5 grid gap-6 @4xl:grid-cols-3">
                <section aria-labelledby="draft-reviews-heading">
                    <h3 id="draft-reviews-heading" class="font-semibold">Draft changes · {{ $optimisations->total() }}</h3>
                    <div class="mt-3 max-h-96 divide-y divide-slate-950/10 overflow-y-auto overscroll-contain text-base sm:text-sm">
                        @forelse ($optimisations as $optimisation)
                            <div class="py-4 first:pt-0 last:pb-0">
                                <p class="font-medium">{{ $optimisation->website->name }}</p>
                                <p class="mt-1 break-all text-slate-500">{{ $optimisation->url }}</p>
                                <p class="mt-2"><a href="{{ $optimisation->page ? route('admin.website-health-report-pages.show', [$optimisation->website, $optimisation->page->website_health_report_id, $optimisation->page]) : route('admin.websites.section', [$optimisation->website, 'content']) }}" class="ui-button ui-button-secondary">Review change</a></p>
                            </div>
                        @empty
                            <p class="text-slate-500">No draft changes awaiting review.</p>
                        @endforelse
                    </div>
                    <div class="mt-4">{{ $optimisations->withQueryString()->links() }}</div>
                </section>
                <section aria-labelledby="content-reviews-heading">
                    <h3 id="content-reviews-heading" class="font-semibold">Content pull requests · {{ $contentReviews->total() }}</h3>
                    <div class="mt-3 max-h-96 divide-y divide-slate-950/10 overflow-y-auto overscroll-contain text-base sm:text-sm">
                        @forelse ($contentReviews as $generation)
                            <div class="py-4 first:pt-0 last:pb-0"><p class="font-medium">{{ $generation->plan->website->name }}</p><p class="mt-1 text-slate-500">Pull request #{{ $generation->pull_request_number }}</p><p class="mt-2"><a href="{{ route('admin.websites.section', [$generation->plan->website, 'content']) }}" class="ui-button ui-button-secondary">Review content</a></p></div>
                        @empty
                            <p class="text-slate-500">No content pull requests awaiting review.</p>
                        @endforelse
                    </div>
                    <div class="mt-4">{{ $contentReviews->withQueryString()->links() }}</div>
                </section>
                <section aria-labelledby="fix-reviews-heading">
                    <h3 id="fix-reviews-heading" class="font-semibold">Website fixes · {{ $remediationReviews->total() }}</h3>
                    <div class="mt-3 max-h-96 divide-y divide-slate-950/10 overflow-y-auto overscroll-contain text-base sm:text-sm">
                        @forelse ($remediationReviews as $run)
                            <div class="py-4 first:pt-0 last:pb-0"><p class="font-medium">{{ $run->report->website->name }}</p><p class="mt-1 text-slate-500">Pull request #{{ $run->pull_request_number }}</p><p class="mt-2"><a href="{{ route('admin.website-health-reports.show', [$run->report->website, $run->report]) }}" class="ui-button ui-button-secondary">Review fixes</a></p></div>
                        @empty
                            <p class="text-slate-500">No website fixes awaiting review.</p>
                        @endforelse
                    </div>
                    <div class="mt-4">{{ $remediationReviews->withQueryString()->links() }}</div>
                </section>
            </div>
        </section>
        <section id="hub-results" aria-labelledby="hub-tab-results" @if ($hubSection !== 'results') hidden @endif>
            <x-seo-impact-reviews :reviews="$impactReviews" />
            @if ($impactReviews->isEmpty())
                <div class="ui-well p-6"><h2 class="font-semibold">No results waiting for you</h2><p class="mt-2 text-base text-slate-600 sm:text-sm">Sitewell will bring live-page issues and four- and eight-week reviews here automatically.</p></div>
            @endif
            <div class="mt-4">{{ $impactReviews->links() }}</div>
        </section>
        <div id="hub-automation" class="space-y-6" aria-labelledby="hub-tab-automation" @if ($hubSection !== 'automation') hidden @endif>
            <div class="@container">
                <div class="grid gap-6 @3xl:grid-cols-2">
                    @foreach (['attention' => 'Needs attention', 'completed' => 'Recently completed'] as $kind => $heading)
                        <section class="ui-panel ui-section min-w-0" aria-labelledby="work-{{ $kind }}-heading">
                            <h2 id="work-{{ $kind }}-heading" class="text-xl font-semibold text-slate-950">{{ $heading }}</h2>
                            <p class="mt-1 text-slate-600 text-base sm:text-sm">{{ $kind === 'attention' ? 'Up to 10 recently updated failures or possibly stalled jobs. Running audits are flagged after 15 minutes; content and fixes after two hours.' : 'The latest 10 finished reports, content changes and website fixes.' }}</p>
                            <div class="mt-5 max-h-96 overflow-y-auto overscroll-contain divide-y divide-slate-950/10">
                                @forelse ($workActivity[$kind] as $item)
                                    <div class="space-y-2 py-4 first:pt-0 last:pb-0">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <a href="{{ $item['url'] }}" class="font-semibold text-slate-950 hover:text-teal-700">{{ $item['website'] }}</a>
                                            <span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-red-50 text-red-700' => $item['status'] === 'Failed', 'bg-amber-50 text-amber-800' => $item['status'] === 'Possibly stalled', 'bg-teal-50 text-teal-800' => $kind === 'completed'])>{{ $item['status'] }}</span>
                                        </div>
                                        <p class="text-slate-600 text-base sm:text-sm">{{ $item['type'] }} · <time datetime="{{ $item['age_at']->toIso8601String() }}" title="{{ $item['age_at']->format('j M Y, H:i').' '.config('app.timezone') }}">{{ $item['age_at']->diffForHumans() }}</time></p>
                                        @if ($kind === 'attention')<p class="line-clamp-3 break-words text-slate-600 text-base sm:text-sm">{{ $item['reason'] }}</p>@endif
                                        <a href="{{ $item['url'] }}" class="ui-button ui-button-secondary">{{ $kind === 'attention' ? 'Investigate' : 'View work' }} →</a>
                                    </div>
                                @empty
                                    <p class="text-slate-500 text-base sm:text-sm">{{ $kind === 'attention' ? 'No failed or stalled work found.' : 'No completed work yet.' }}</p>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>

            <section id="content-queue" class="ui-panel ui-section @container scroll-mt-20" aria-labelledby="content-queue-heading">
                <h2 id="content-queue-heading" class="text-xl font-semibold text-slate-950">Content queue</h2>
                <p class="mt-1 text-slate-600 text-base sm:text-sm">Requests awaiting preparation across websites. Sites needing attention appear first. Each run selects eligible work; it may not clear the whole queue.</p>
                <div class="mt-5 max-h-96 overflow-y-auto overscroll-contain divide-y divide-slate-950/10">
                    @forelse ($contentQueue as $item)
                        <div class="grid gap-3 py-4 first:pt-0 last:pb-0 @3xl:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_auto] @3xl:items-center">
                            <div class="min-w-0">
                                <a href="{{ \App\Support\WebsiteNavigation::routeFor($item['website'], 'content') }}" class="font-semibold text-slate-950 hover:text-teal-700">{{ $item['website']->name }}</a>
                                <p class="mt-1 text-slate-500 text-base sm:text-sm">{{ number_format($item['count']) }} {{ Str::plural('request', $item['count']) }} waiting</p>
                            </div>
                            <div>
                                <span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-amber-50 text-amber-800' => $item['state'] === 'Needs setup', 'bg-slate-100 text-slate-700' => $item['state'] === 'Paused', 'bg-teal-50 text-teal-800' => $item['state'] === 'Scheduled'])>{{ $item['state'] }}</span>
                                @if ($item['next_run_at'])
                                    <p class="mt-2 font-medium text-slate-900 text-base sm:text-sm"><time datetime="{{ $item['next_run_at']->toIso8601String() }}">{{ $item['next_run_at']->format('D j M, H:i') }}</time> · {{ config('app.timezone') }}</p>
                                @endif
                                <p class="mt-1 text-slate-600 text-base sm:text-sm">{{ $item['reason'] }}</p>
                            </div>
                            <a href="{{ $item['url'] }}" class="ui-button ui-button-secondary">{{ $item['action'] }} →</a>
                        </div>
                    @empty
                        <p class="text-slate-500 text-base sm:text-sm">No content requests awaiting preparation.</p>
                    @endforelse
                </div>
            </section>

            <section class="ui-panel ui-section @container" aria-labelledby="schedule-heading">
                <h2 id="schedule-heading" class="text-xl font-semibold text-slate-950">Upcoming schedule</h2>
                <p class="mt-1 text-slate-600 text-base sm:text-sm">Next health checks and content preparation runs. Times shown in {{ config('app.timezone') }}.</p>
                <div class="mt-4 max-h-96 overflow-y-auto overscroll-contain divide-y divide-slate-950/10">
                    @forelse ($automationSchedule as $item)
                        <div class="flex flex-col gap-2 py-4 @2xl:flex-row @2xl:items-center @2xl:justify-between">
                            <div><a href="{{ route('admin.websites.section', [$item['website'], $item['type'] === 'Health report' ? 'health' : 'content']) }}" class="font-medium text-slate-950 hover:text-teal-700">{{ $item['website']->name }}</a><p class="mt-1 text-slate-600 text-base sm:text-sm">{{ $item['type'] }}</p></div>
                            <div class="text-base sm:text-sm @2xl:text-right"><time datetime="{{ $item['next_run_at']->toIso8601String() }}" class="font-semibold tabular-nums text-slate-900">{{ $item['next_run_at']->format('D j M, H:i') }}</time><p class="mt-1 text-slate-500">{{ $item['next_run_at']->diffForHumans() }}</p></div>
                        </div>
                    @empty
                        <p class="py-4 text-slate-500 text-base sm:text-sm">No upcoming automation is scheduled.</p>
                    @endforelse
                </div>
            </section>
        </div>
        <section id="hub-websites" aria-labelledby="hub-tab-websites" @if ($hubSection !== 'websites') hidden @endif>
            <x-admin-website-directory :websites="$websiteDirectory" />
        </section>
    </div>
@endsection
