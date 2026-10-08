@extends('layouts.marketing')

@section('title', 'Website review for '.$audit->domain)
@section('meta_description', 'Your private Sitewell website review and proposed next steps.')

@section('content')
@php
    $findings = collect($audit->findings ?? []);
    $fixCount = $findings->whereIn('severity', ['warning', 'failed'])->count();
    $healthScore = data_get($audit->insights, 'health_score');
    $healthScore ??= $findings->isNotEmpty() ? (int) round($findings->where('severity', 'passed')->count() / $findings->count() * 100) : null;
    $googleRankingCount = data_get($audit->insights, 'seo.organic_keywords');
    $pageCount = data_get($audit->insights, 'pages_matching_domain', data_get($audit->insights, 'pages_listed'));
    $pageCountPartial = (bool) data_get($audit->insights, 'pages_partial', false);
    $hasChecks = $findings->isNotEmpty();
    $healthStatus = $healthScore === null ? 'Not available' : ($healthScore >= 80 ? 'Looking healthy' : ($healthScore >= 50 ? 'Needs work' : 'Needs attention'));
    $pagesListed = data_get($audit->insights, 'pages_listed');
    $pagesPartial = (bool) data_get($audit->insights, 'pages_partial', false);
    $pagesMismatchedDomain = (int) data_get($audit->insights, 'pages_mismatched_domain', 0);
    $pagesMismatchedHost = data_get($audit->insights, 'pages_mismatched_host');
    $seo = data_get($audit->insights, 'seo');
    $hasGoogleRankings = collect(data_get($seo, 'keywords', []))->contains(fn (mixed $keyword): bool => is_array($keyword) && filled($keyword['term'] ?? null) && is_numeric($keyword['position'] ?? null) && $keyword['position'] > 0);
    $hasAiAppearance = collect(data_get($audit->insights, 'ai_visibility.results', []))->contains(fn (mixed $result): bool => is_array($result) && ($result['status'] ?? null) === 'completed' && (($result['website_mentioned'] ?? false) === true || ($result['website_cited'] ?? false) === true));
    $competitors = data_get($audit->insights, 'competitors');
    $aiVisibility = data_get($audit->insights, 'ai_visibility');
    $fullReportStatus = data_get($audit->insights, 'full_report.status', 'completed');
    $isFinalisingReport = $showDetails && $audit->isReadyToDisplay() && (in_array($fullReportStatus, ['queued', 'running'], true) || ($fullReportStatus === 'completed' && data_get($aiVisibility, 'status') === 'pending'));
    $visitorRange = $projection !== null ? number_format($projection['six_month_low']).'–'.number_format($projection['six_month_high']) : null;
@endphp
<section @if ($engagementUrl) data-audit-engagement-url="{{ $engagementUrl }}" data-audit-id="{{ $audit->public_id }}" data-csrf-token="{{ csrf_token() }}" @endif data-marketing-events="{{ json_encode($marketingEvents) }}" class="px-3 pt-1 pb-16 sm:px-6 sm:pt-2 sm:pb-24" aria-labelledby="audit-title">
    <div @class(['mx-auto grid max-w-7xl gap-6 rounded-3xl bg-lichen px-5 sm:px-10', 'py-6 sm:py-8' => $audit->isReadyToDisplay(), 'py-10 sm:py-14' => ! $audit->isReadyToDisplay()])>
        @if ($audit->isReadyToDisplay())
            <header @class(['grid min-w-0 gap-5 lg:items-stretch lg:gap-10', 'lg:grid-cols-[minmax(0,1fr)_26rem]' => $screenshotUrl])>
                <div data-audit-intro class="flex min-w-0 flex-col justify-between gap-6 lg:pb-7">
                    <div class="grid min-w-0 gap-3">
                    @if ($screenshotUrl)
                        <figure class="order-last grid w-full gap-2 pt-4 lg:hidden">
                            <div class="rounded-xl bg-white p-1 ring-1 ring-ink/10">
                                <img src="{{ $screenshotUrl }}" alt="" width="1024" height="640" class="aspect-[8/5] w-full rounded-[min(1vw,8px)] object-cover object-top outline-1 -outline-offset-1 outline-black/5" loading="eager" decoding="async">
                            </div>
                            <figcaption class="truncate text-center text-base font-medium text-garden" title="{{ $audit->domain }}">{{ $audit->domain }}</figcaption>
                        </figure>
                    @else
                        <p class="min-w-0 truncate text-base font-medium text-garden sm:text-sm" title="{{ $audit->domain }}">{{ $audit->domain }}</p>
                    @endif
                @if ($hasAiAppearance)
                    <p data-audit-ai-appearance class="text-base font-medium text-emerald-800 sm:text-sm">Your site appears in sampled AI answers.</p>
                @endif
                <h1 id="audit-title" class="max-w-[24ch] text-4xl font-medium tracking-tight text-balance sm:text-5xl">{{ $showDetails ? 'Your full website audit.' : 'Your website audit is ready.' }}</h1>
                <p class="mt-4 max-w-[56ch] text-pretty text-base text-ink/65">{{ $showDetails ? "Your website checks, Google rankings, competitors and AI visibility — together in one report." : ($hasGoogleRankings ? "We’ve found your Google rankings, your competitors' websites and where you show for AI answers. View the full report below to see how to get more customers via search." : "We’ve found your Google rankings, competitors and where you show for AI answers. View the full report below to see how to get more customers via search.") }}</p>
                    </div>
    <dl class="grid grid-cols-4 items-start gap-2 text-xs sm:flex sm:flex-wrap sm:gap-x-8 sm:gap-y-3 sm:text-sm">
        <div class="grid min-w-0 gap-1"><dt class="truncate font-medium text-ink/65"><span class="max-sm:hidden">Website health</span><span class="sm:hidden">Health</span></dt><dd data-audit-health-score class="truncate text-2xl font-medium tracking-tight tabular-nums text-ink sm:text-3xl">{{ $healthScore !== null ? $healthScore.'%' : '—' }}</dd><dd data-audit-health-status class="sr-only">{{ $healthStatus }}</dd></div>
        <div class="grid min-w-0 gap-1"><dt class="truncate font-medium text-ink/65"><span class="max-sm:hidden">Website fixes</span><span class="sm:hidden">Fixes</span></dt><dd data-audit-fix-count class="truncate text-2xl font-medium tracking-tight tabular-nums text-ink sm:text-3xl">{{ $hasChecks ? number_format($fixCount) : '—' }}</dd><dd data-audit-fix-status class="sr-only">{{ $hasChecks ? ($fixCount > 0 ? 'Issues found' : 'None flagged') : 'Not available' }}</dd></div>
        <div class="grid min-w-0 gap-1"><dt class="truncate font-medium text-ink/65" title="Pages listed for this website in its sitemap"><span class="max-sm:hidden">Pages found</span><span class="sm:hidden">Pages</span></dt><dd data-audit-page-count class="truncate text-2xl font-medium tracking-tight tabular-nums text-ink sm:text-3xl">{{ is_numeric($pageCount) ? number_format((int) $pageCount).($pageCountPartial ? '+' : '') : '—' }}</dd></div>
        @if (is_numeric($googleRankingCount) && $googleRankingCount >= 0)
            <div class="grid min-w-0 gap-1"><dt class="truncate font-medium text-ink/65" title="Estimated number of Google search terms this website ranks for"><span class="max-sm:hidden">Google rankings</span><span class="sm:hidden">Rankings</span></dt><dd data-audit-ranking-count class="truncate text-2xl font-medium tracking-tight tabular-nums text-ink sm:text-3xl">{{ '~'.number_format((int) $googleRankingCount) }}</dd></div>
        @endif
        @if ($healthScore !== null)
            <div data-audit-health-meter role="meter" aria-label="Homepage checks passed" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $healthScore }}" class="sr-only"></div>
        @endif
    </dl>
                </div>
                @if ($screenshotUrl)
                    <figure data-audit-desktop-preview class="grid w-full gap-2 max-lg:hidden">
                        <div class="rounded-[calc(var(--preview-radius)+--spacing(1.5))] bg-white p-1.5 ring-1 ring-ink/10 [--preview-radius:min(1vw,12px)]">
                            <img src="{{ $screenshotUrl }}" alt="" width="1024" height="640" class="aspect-[8/5] w-full rounded-(--preview-radius) object-cover object-top outline-1 -outline-offset-1 outline-black/5" loading="eager" decoding="async">
                        </div>
                        <figcaption class="truncate text-center text-base font-medium text-garden sm:text-sm" title="{{ $audit->domain }}">{{ $audit->domain }}</figcaption>
                    </figure>
                @endif
            </header>
        @else
        <header @class(['grid min-w-0 gap-6 lg:items-center lg:gap-10', 'lg:grid-cols-[minmax(0,1fr)_18rem]' => $screenshotUrl])>
            <div class="grid min-w-0 content-start gap-3">
                <p class="text-base font-medium text-garden sm:text-sm">{{ $showDetails ? 'Full website review' : 'Your website review' }}</p>
                <h1 id="audit-title" class="w-full min-w-0 max-w-[24ch] truncate text-4xl font-medium tracking-tight text-balance sm:text-5xl" title="{{ $audit->domain }}">{{ $audit->domain }}</h1>
            </div>
            @if ($screenshotUrl)
                <figure @class(['max-w-sm rounded-[calc(var(--preview-radius)+--spacing(1.5))] bg-white p-1.5 ring-1 ring-ink/10 [--preview-radius:min(1vw,12px)] lg:justify-self-end', 'max-lg:hidden' => $audit->isReadyToDisplay() && ! $showDetails])>
                    <img src="{{ $screenshotUrl }}" alt="" width="1024" height="640" class="aspect-[8/5] w-full rounded-(--preview-radius) object-cover object-top outline-1 -outline-offset-1 outline-black/5" loading="eager" decoding="async">
                </figure>
            @endif
        </header>
        @endif

        @if (session('report_email_status') && (! $audit->personal_review_requested_at || $audit->personal_review_queued_at))
            <p role="status" class="rounded-2xl bg-emerald-50 px-5 py-4 text-base text-emerald-900 ring-1 ring-emerald-200/70">{{ session('report_email_status') }}</p>
        @endif
        @if ($showDetails && $audit->isReadyToDisplay() && ! $isFinalisingReport && $fullReportStatus !== 'completed')
            <section role="status" class="grid gap-3 rounded-2xl bg-white p-5 ring-1 ring-ink/10 sm:p-6" aria-labelledby="audit-full-research-title">
                <h2 id="audit-full-research-title" class="text-xl font-medium tracking-tight">{{ in_array($fullReportStatus, ['queued', 'running'], true) ? 'Preparing the rest of this report.' : ($fullReportStatus === 'failed' ? 'The extra research couldn’t finish.' : 'The extra research hasn’t been run yet.') }}</h2>
                <p class="max-w-[60ch] text-pretty text-base text-ink/65">{{ in_array($fullReportStatus, ['queued', 'running'], true) ? 'Page-one rankings, sitemap counts, backlinks and competitor comparisons are loading. AI checks follow.' : ($fullReportStatus === 'failed' ? 'Your saved results are below. Talk to Ross if you’d like help with the missing research.' : 'Your saved results are below. The remaining research hasn’t been requested yet.') }}</p>
                @if (auth()->user()?->isAdmin() && in_array($fullReportStatus, ['deferred', 'failed'], true))
                    <form method="POST" action="{{ route('admin.onboarding.audits.generate', $audit) }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-full bg-garden px-5 py-3 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden">{{ $fullReportStatus === 'failed' ? 'Retry full research' : 'Load full research' }}</button>
                    </form>
                @endif
            </section>
        @endif
        @if ($isFinalisingReport)
            <script>
                (() => {
                    const statusUrl = @js($researchStatusUrl);
                    const initialStatus = @js($fullReportStatus);
                    const initialAiStatus = @js(data_get($aiVisibility, 'status'));
                    const timer = window.setInterval(async () => {
                        try {
                            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                            if (! response.ok) return;
                            const result = await response.json();
                            if (result.full_report_status !== initialStatus || result.ai_visibility_status !== initialAiStatus) {
                                window.clearInterval(timer);
                                window.location.reload();
                            }
                        } catch (_) {}
                    }, 5000);
                })();
            </script>
        @endif

        @if ($audit->status !== \App\Models\WebsiteAudit::STATUS_FAILED && ! $audit->isReadyToDisplay())
            <div id="audit-progress" data-status-url="{{ route('marketing.website-audits.status', $audit) }}" class="grid min-h-72 place-items-center border-t border-ink/10 py-10">
                <div role="status" class="grid justify-items-center gap-6 text-center">
                    <div class="relative grid size-20 place-items-center" aria-hidden="true">
                        <div class="absolute inset-0 rounded-full border-2 border-garden/15"></div>
                        <div class="absolute inset-0 animate-spin rounded-full border-2 border-transparent border-t-garden motion-reduce:animate-none"></div>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-8 text-garden">
                            <circle cx="10.8" cy="10.8" r="5.8" />
                            <path d="m15.2 15.2 4.3 4.3" />
                        </svg>
                    </div>
                    <div class="grid gap-2">
                        <h2 class="text-2xl font-medium tracking-tight text-balance sm:text-3xl">Checking your website.</h2>
                        <p class="max-w-[44ch] text-pretty text-base text-ink/65">Checking your website and preparing your report.</p>
                    </div>
                    <p class="text-pretty text-base text-ink/50 sm:text-sm">Your results will appear here automatically.</p>
                </div>
            </div>
            <script>
                (() => {
                    const progress = document.getElementById('audit-progress');
                    const checkStatus = async () => {
                        try {
                            const response = await fetch(progress.dataset.statusUrl, { headers: { Accept: 'application/json' } });
                            if (! response.ok) return;
                            const result = await response.json();
                            if (result.completed || result.failed) window.location.reload();
                        } catch (_) {
                            // Try again on the next poll.
                        }
                    };
                    window.setInterval(checkStatus, 2000);
                })();
            </script>
        @elseif ($audit->status === \App\Models\WebsiteAudit::STATUS_FAILED)
            <div class="grid gap-4 border-t border-ink/10 pt-8">
                <h2 class="max-w-[35ch] text-2xl font-medium tracking-tight text-balance">We could not review your website.</h2>
                <p class="max-w-[56ch] text-pretty text-base text-ink/65">{{ preg_match('/HTTP (401|403)\b/', (string) $audit->analysis_error) ? 'Your website restricted access to our automated check. We can help review it with you.' : 'It may be unavailable or blocking automated checks. Check the address and try again.' }}</p>
                <p><a href="{{ route('marketing.free-site-audit') }}" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Try another website address</a></p>
                <p><a data-audit-book-call href="{{ route('marketing.ppc.book') }}" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Book a call with Ross for help reviewing your website</a></p>
            </div>
        @elseif (! $showDetails)
            @include('marketing.audit-email-gate')
        @else
            <div class="border-t border-ink/10 pt-6">
                <div @class(['relative isolate overflow-hidden rounded-2xl', 'max-h-104' => $isFinalisingReport])>
                    @if ($isFinalisingReport)
                        <div data-audit-full-report-loader role="status" class="absolute inset-0 z-10 grid place-items-center bg-white/65 p-6">
                            <div class="grid justify-items-center gap-6 text-center">
                                <div class="relative grid size-20 place-items-center" aria-hidden="true">
                                    <div class="absolute inset-0 rounded-full border-2 border-garden/15"></div>
                                    <div class="absolute inset-0 animate-spin rounded-full border-2 border-transparent border-t-garden motion-reduce:animate-none"></div>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-8 text-garden">
                                        <circle cx="10.8" cy="10.8" r="5.8" />
                                        <path d="m15.2 15.2 4.3 4.3" />
                                    </svg>
                                </div>
                                <div class="grid gap-2">
                                    <h2 class="text-2xl font-medium tracking-tight text-balance sm:text-3xl">Finalising your full report.</h2>
                                    <p class="text-pretty text-base text-ink/65">This won’t take long. Your report will appear automatically.</p>
                                </div>
                            </div>
                        </div>
                    @endif
                <div data-audit-report-surface @if ($isFinalisingReport) data-audit-full-report-background aria-hidden="true" inert @endif @class(['grid overflow-hidden rounded-2xl bg-white', 'pointer-events-none select-none blur-[3px]' => $isFinalisingReport])>
                    @include('marketing.audit-category-scores')
                    <div class="grid gap-8 px-4 pb-6 sm:px-6 sm:pb-8">
                        <section class="grid gap-6" aria-labelledby="audit-numbers-title">

                            <div class="grid gap-2">
                                <h2 id="audit-numbers-title" class="max-w-[35ch] text-3xl font-medium tracking-tight text-balance">Search overview.</h2>
                                <p class="max-w-[56ch] text-pretty text-base text-ink/65">Your current search visibility at a glance.</p>
                            </div>

                            @if ($showDetails && $seo !== null)
                                <dl class="grid grid-cols-2 gap-x-6 gap-y-6 sm:grid-cols-4">
                                    <div class="grid content-start gap-1 border-t border-ink/10 pt-4"><dt class="text-base text-ink/65 sm:text-sm">Terms in the top 10</dt><dd class="order-first text-3xl font-medium tracking-tight tabular-nums text-ink">{{ number_format($seo['top_10_keywords']) }}</dd></div>
                                    <div class="grid content-start gap-1 border-t border-ink/10 pt-4"><dt class="text-base text-ink/65 sm:text-sm">Terms in the top 3</dt><dd class="order-first text-3xl font-medium tracking-tight tabular-nums text-ink">{{ number_format($seo['top_3_keywords']) }}</dd></div>
                                    <div class="grid content-start gap-1 border-t border-ink/10 pt-4"><dt class="text-base text-ink/65 sm:text-sm">Est. monthly organic visits</dt><dd class="order-first text-3xl font-medium tracking-tight tabular-nums text-ink">{{ number_format($seo['estimated_monthly_visits']) }}</dd></div>
                                    @if ($seo['referring_domains'] !== null)
                                        <div class="grid content-start gap-1 border-t border-ink/10 pt-4"><dt class="text-base text-ink/65 sm:text-sm">Referring domains</dt><dd class="order-first text-3xl font-medium tracking-tight tabular-nums text-ink">{{ number_format($seo['referring_domains']) }}</dd></div>
                                    @endif
                                </dl>
                            @endif
                            @if ($showDetails && $pagesMismatchedDomain > 0)
                                <p class="rounded-2xl bg-amber-50 p-4 text-base text-amber-900 ring-1 ring-amber-200/70 sm:text-sm">Sitemap issue: {{ number_format($pagesMismatchedDomain) }} {{ $pagesMismatchedDomain === 1 ? 'URL points' : 'URLs point' }} {{ $pagesMismatchedHost ? 'to '.$pagesMismatchedHost.' instead of '.$audit->domain : 'away from '.$audit->domain }}. Update the sitemap to use your live domain.</p>
                            @endif
                            <p class="text-pretty text-base text-ink/55 sm:text-sm">Website health reflects the homepage checks run. Search figures are third-party estimates.</p>
                            @if ($pagesListed === null || $seo === null)
                                <p class="text-pretty text-base text-ink/55 sm:text-sm">{{ $seo === null ? 'Search estimates are unavailable for this review.' : '' }} {{ $pagesListed === null ? 'A page count needs an accessible XML sitemap.' : '' }}</p>
                            @endif

                        </section>

                        @if ($seo !== null)
                            <section class="grid gap-5 border-t border-ink/10 pt-8" aria-labelledby="audit-search-title">
                                <div class="grid gap-2">
                                    <h2 id="audit-search-title" class="max-w-[35ch] text-2xl font-medium tracking-tight text-balance">Search snapshot.</h2>
                                    <p class="max-w-[56ch] text-pretty text-base text-ink/65">Google search estimates for {{ $seo['location_code'] === 2826 ? 'the UK' : 'the selected market' }}{{ isset($seo['retrieved_at']) ? ', checked '.\Illuminate\Support\Carbon::parse($seo['retrieved_at'])->format('j M Y') : '' }}. Monthly search volume is demand for a term, not visits to your site.</p>
                                </div>
                                @if ($rankings['page_one'] !== [] || $rankings['striking_distance'] !== [] || $rankings['other'] !== [])
                                    <div @class(['grid gap-8', 'lg:grid-cols-2' => $rankings['page_one'] !== [] && $rankings['striking_distance'] !== []])>
                                        @foreach (['page_one' => ['Page one rankings', 'Positions 1–10'], 'striking_distance' => ['Within striking distance', 'Positions 11–30'], 'other' => ['Rankings found', 'Best positions in this sample']] as $group => [$heading, $description])
                                            @if ($rankings[$group] !== [])
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-t border-ink/15 pt-4">
                                                        <h3 class="text-lg font-medium tracking-tight text-ink">{{ $heading }}</h3>
                                                        <p class="text-sm text-ink/55">{{ $description }}</p>
                                                    </div>
                                                    <div class="overflow-x-auto">
                                                        <table class="w-full min-w-md border-collapse text-left text-sm">
                                                            <thead class="border-b border-ink/15 text-ink/60">
                                                                <tr><th scope="col" class="py-3 pr-3 font-medium">Ranking search</th><th scope="col" class="px-2 py-3 text-right font-medium">Position</th><th scope="col" class="py-3 pl-2 text-right font-medium">Monthly searches</th></tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($rankings[$group] as $keyword)
                                                                    <tr class="border-b border-ink/10 last:border-0">
                                                                        <td class="py-3 pr-3 text-ink">{{ $keyword['term'] }}</td>
                                                                        <td class="px-2 py-3 text-right tabular-nums text-ink/65">{{ $keyword['position'] }}</td>
                                                                        <td class="py-3 pl-2 text-right tabular-nums text-ink/65">{{ isset($keyword['monthly_searches']) ? number_format($keyword['monthly_searches']) : '—' }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                    <p class="text-pretty text-base text-ink/55 sm:text-sm">Showing selected terms from a sample of {{ $seo['sample_size'] ?? count($seo['keywords']) }}; volumes and traffic are third-party estimates.</p>
                                @else
                                    <p class="text-pretty text-base text-ink/65">{{ $seo['organic_keywords'] === 0 ? 'No ranking terms were found in this dataset yet.' : 'Keyword details are unavailable for this review.' }}</p>
                                @endif
                            </section>
                        @endif

                        @if ($seo !== null && $fullReportStatus === 'completed')
                            <section class="grid gap-5 border-t border-ink/10 pt-8" aria-labelledby="audit-competitors-title">
                                <div class="grid gap-2">
                                    <h2 id="audit-competitors-title" class="text-2xl font-medium tracking-tight text-balance">Your Google search competitors.</h2>
                                    @if (is_array($competitors) && isset($competitors['domain']))
                                        <p class="max-w-[60ch] text-pretty text-base text-ink/65">{{ $competitors['domain'] }} ranks for {{ number_format($competitors['shared_terms']) }} of the same Google search terms. This is a search competitor, based on shared rankings.</p>
                                    @else
                                        <p class="max-w-[60ch] text-pretty text-base text-ink/65">We couldn't make a useful competitor comparison from this sample yet.</p>
                                    @endif
                                </div>
                                @if (! empty($competitors['others']))
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($competitors['others'] as $other)
                                            <span class="rounded-full bg-white px-3 py-1.5 text-base text-ink/70 ring-1 ring-ink/10 sm:text-sm">{{ $other['domain'] }} · {{ number_format($other['shared_terms']) }} shared terms</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if (is_array($competitors) && ! empty($competitors['terms']))
                                    <div class="-mx-5 -my-2 overflow-x-auto whitespace-nowrap sm:-mx-10">
                                        <div class="inline-block min-w-full px-5 py-2 align-middle sm:px-10">
                                        <table class="w-full min-w-md border-collapse text-left text-base sm:text-sm">
                                            <thead class="border-b border-ink/10 text-ink/60"><tr><th scope="col" class="whitespace-nowrap py-4 pr-4 font-medium">Shared search</th><th scope="col" class="whitespace-nowrap px-4 py-4 text-right font-medium">You</th><th scope="col" class="whitespace-nowrap py-4 pl-4 text-right font-medium">{{ $competitors['domain'] }}</th></tr></thead>
                                            <tbody>
                                                @foreach ($competitors['terms'] as $term)
                                                    <tr class="border-b border-ink/10 last:border-0"><td class="py-3 pr-4 text-ink">{{ $term['term'] }}</td><td @class(['px-4 py-3 text-right tabular-nums', 'font-medium text-emerald-700' => $term['our_position'] < $term['competitor_position'], 'text-ink/65' => $term['our_position'] >= $term['competitor_position']])>{{ $term['our_position'] }}</td><td @class(['py-3 pl-4 text-right tabular-nums', 'font-medium text-emerald-700' => $term['competitor_position'] < $term['our_position'], 'text-ink/65' => $term['competitor_position'] >= $term['our_position']])>{{ $term['competitor_position'] }}</td></tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                    <p class="text-base text-ink/55 sm:text-sm">A small sample of estimated Google positions, checked {{ \Illuminate\Support\Carbon::parse($competitors['retrieved_at'])->format('j M Y') }}.</p>
                                @endif
                            </section>
                        @endif

                        @if ($fullReportStatus === 'completed')
                        <section class="grid gap-4 border-t border-ink/10 pt-8" aria-labelledby="audit-ai-title">
                                <div class="grid gap-2">
                                    <h2 id="audit-ai-title" class="text-2xl font-medium tracking-tight text-balance">AI search check.</h2>
                                    <p class="max-w-[60ch] text-pretty text-base text-ink/65">Questions suggested by Google terms your site ranks for. These are sampled OpenAI answers; AI tools do not have fixed rankings for every question.</p>
                                </div>
                                @if (is_array($aiVisibility))
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        @foreach ($aiVisibility['questions'] ?? [] as $index => $question)
                                            @php($result = $aiVisibility['results'][$index] ?? null)
                                            <div class="grid content-start gap-3 rounded-2xl bg-white p-5 ring-1 ring-ink/10 sm:p-6">
                                                <p class="font-medium text-ink">“{{ $question }}”</p>
                                                @if (($result['status'] ?? null) === 'completed')
                                                    <p @class(['font-medium', 'text-emerald-800' => $result['website_cited'], 'text-amber-900' => ! $result['website_cited']])>{{ $result['website_cited'] ? 'Your website was cited.' : ($result['website_mentioned'] ? 'Your website was mentioned, but not cited.' : 'Your website was not seen.') }}</p>
                                                    <p class="text-base text-ink/55 sm:text-sm">OpenAI check on {{ \Illuminate\Support\Carbon::parse($result['checked_at'])->format('j M Y') }}.</p>
                                                @elseif ($aiVisibility['status'] === 'pending')
                                                    <p class="text-base text-ink/65" role="status">Checking this answer.</p>
                                                @else
                                                    <p class="text-base text-ink/65">A live answer is unavailable.</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    <p class="text-base text-ink/55 sm:text-sm">These answers are samples, not a measure of all AI searches.</p>
                                @else
                                    <p class="text-base text-ink/65">There isn't enough unbranded search data to choose a useful question yet.</p>
                                @endif
                        </section>
                        @endif

                        <section class="grid gap-5 border-t border-ink/10 pt-8" aria-labelledby="audit-findings-title">
                            <div class="grid gap-2"><h2 id="audit-findings-title" class="text-2xl font-medium tracking-tight text-balance">Your website checks.</h2><p class="max-w-[56ch] text-base text-ink/65">These checks cover the homepage and basic search setup. They aren’t a complete review of every page.</p></div>
                            <dl class="grid gap-3 sm:grid-cols-2">
                                @forelse ($findings as $finding)
                                    <div class="grid content-start gap-2 rounded-2xl bg-white p-5 ring-1 ring-ink/10">
                                        <dt class="font-medium">{{ $finding['title'] ?? 'Website check' }}</dt>
                                        <dd @class(['text-base font-medium sm:text-sm', 'text-emerald-800' => ($finding['severity'] ?? '') === 'passed', 'text-amber-900' => ($finding['severity'] ?? '') !== 'passed'])>{{ ($finding['severity'] ?? '') === 'passed' ? 'Passed' : 'Needs attention' }}</dd>
                                        <dd class="text-base text-ink/65">{{ $finding['message'] ?? 'No detail available for this check.' }}</dd>
                                    </div>
                                @empty
                                    <p class="text-base text-ink/65">Detailed website checks aren’t available for this report.</p>
                                @endforelse
                            </dl>
                        </section>
                        <section class="grid gap-6 rounded-3xl bg-white p-5 ring-1 ring-ink/10 sm:p-8" aria-labelledby="audit-projection-title">
                            <div class="grid gap-2">
                                <p class="text-base font-medium text-garden sm:text-sm">Search opportunity</p>
                                <h2 id="audit-projection-title" class="max-w-[35ch] text-3xl font-medium tracking-tight text-balance">Where this could be in six months.</h2>
                                <p class="max-w-[56ch] text-pretty text-base text-ink/65">Estimated monthly visits from Google search.</p>
                            </div>
                            @if ($projection !== null)
                                <div class="grid items-center gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                                    <div class="grid gap-2 rounded-2xl bg-lichen p-5 sm:p-6">
                                        <p class="text-base text-ink/65 sm:text-sm">Today</p>
                                        <p class="text-4xl font-medium tracking-tight tabular-nums text-ink sm:text-5xl">{{ number_format($projection['baseline_monthly_visits']) }}</p>
                                    </div>
                                    <div class="flex justify-center text-garden" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 sm:hidden">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                                        </svg>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden size-6 sm:block">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
                                    </div>
                                    <div class="grid gap-2 rounded-2xl bg-garden/10 p-5 sm:p-6">
                                        <p class="text-base font-medium text-garden sm:text-sm">Possible in six months</p>
                                        <p class="text-4xl font-medium tracking-tight tabular-nums text-ink sm:text-5xl">{{ number_format($projection['six_month_low']) }}–{{ number_format($projection['six_month_high']) }}</p>
                                    </div>
                                </div>
                                <p class="max-w-[56ch] text-pretty text-base text-ink/65">{{ ($projection['model'] ?? null) === 'comparable_pages_v1' ? 'This scenario includes relevant existing and potential new page topics, checked against comparable businesses. It assumes the agreed work is completed; it is not a guarantee.' : 'This range assumes we improve pages already ranking just outside page one. It is an illustration, not a guarantee.' }}</p>
                                <details class="group border-t border-ink/10 pt-5">
                                    <summary class="cursor-pointer font-medium text-garden marker:text-garden">How we estimated this</summary>
                                    <p class="max-w-[65ch] pt-3 text-pretty text-base text-ink/65 sm:text-sm">{{ $projection['method'] }} Search volumes and the current visit count come from third-party estimates.</p>
                                </details>
                                @if (($projection['model'] ?? null) === 'comparable_pages_v1')
                                    <section class="grid gap-4 border-t border-ink/10 pt-6" aria-labelledby="audit-opportunity-evidence-title">
                                        <h3 id="audit-opportunity-evidence-title" class="text-xl font-medium">What supports this estimate</h3>
                                        <p class="text-base text-ink/65">Comparable businesses: {{ collect(data_get($audit->insights, 'opportunity.comparables', []))->pluck('domain')->implode(', ') }}.</p>
                                        <dl class="grid gap-4 sm:grid-cols-2">
                                            @foreach ($projection['pages'] as $page)
                                                <div class="grid gap-2 rounded-2xl bg-white/70 p-5">
                                                    <dt class="font-medium">{{ $page['label'] }}</dt>
                                                    <dd class="text-base text-ink/65">{{ $page['reason'] }}</dd>
                                                    <dd class="text-sm text-ink/65">{{ $page['work'] }} · {{ number_format($page['modelled_search_volume']) }} modelled monthly searches.</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </section>
                                @endif
                            @else
                                <p class="max-w-[56ch] text-pretty text-base text-ink/65">We couldn’t produce a reliable six-month estimate from the data returned. The rankings and checks above still show where to start.</p>
                                @if (auth()->user()?->isAdmin() && filled(data_get($audit->insights, 'opportunity.reason')))
                                    <p class="text-base text-ink/55 sm:text-sm">Forecast diagnostic: {{ data_get($audit->insights, 'opportunity.reason') }}</p>
                                @endif
                            @endif
                        </section>

                        <div data-audit-next-step class="grid gap-6 border-t border-ink/10 pt-8" aria-labelledby="audit-plan-title">
                            <h2 id="audit-plan-title" class="max-w-[35ch] text-3xl font-medium tracking-tight text-balance">The improvements we could take care of.</h2>
                            <p class="max-w-[60ch] text-pretty text-base text-ink/65">The check gives us a starting point. Ross will review what matters for your business, then we’ll agree the work Sitewell would handle for you.</p>
                            <ol class="grid" role="list">
                                @foreach ([
                                    ['Fix the website issues.', 'Resolve the flagged technical problems, then check important pages for crawl, mobile and usability issues.'],
                                    ['Improve existing content.', 'Review your current pages against what customers search for. Refresh weak copy, titles and internal links.'],
                                    ['Create content for missed searches.', 'Build useful service pages and answer buyer questions clearly for people, search engines and AI tools.'],
                                    ['Strengthen the website and its reputation.', 'Keep pages current, check backlinks and earn relevant mentions and links from credible sources.'],
                                    ['Measure and keep improving.', 'Track enquiries, Google and Bing search performance, and AI citations where available. Then set the next priorities.'],
                                ] as $index => [$title, $description])
                                    <li class="grid grid-cols-[2rem_minmax(0,1fr)] gap-4 border-t border-ink/10 py-5 first:border-t-0 first:pt-0 last:pb-0 sm:grid-cols-[2.5rem_minmax(0,1fr)] sm:gap-6">
                                        <span class="text-base font-medium tabular-nums text-garden">{{ sprintf('%02d', $index + 1) }}</span>
                                        <div class="grid gap-1.5">
                                            <h3 class="max-w-[40ch] text-xl font-medium tracking-tight text-balance">{{ $title }}</h3>
                                            <p class="max-w-[56ch] text-pretty text-base text-ink/65">{{ $description }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>

                        @if ($goalUrl || $audit->personal_review_requested_at)
                            <div id="audit-follow-up" class="grid gap-6 scroll-mt-24">
                                @if ($audit->personal_review_requested_at && ! $audit->personal_review_queued_at)
                                    <section role="status" aria-labelledby="audit-request-received-title" class="flex items-start gap-4 rounded-3xl bg-black p-6 text-white ring-1 ring-black sm:p-8">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-emerald-400/15 text-emerald-300" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                                        </span>
                                        <div class="grid gap-2">
                                            <h2 id="audit-request-received-title" class="text-2xl font-medium tracking-tight text-balance sm:text-3xl">Your video is next.</h2>
                                            <p class="max-w-[60ch] text-pretty text-base text-white/80">Ross will review {{ $audit->domain }} and email your video within one working day.</p>
                                        </div>
                                    </section>
                                @endif
                                @if ($goalUrl)
                                    <section aria-labelledby="audit-goal-title" class="grid gap-3">
                                        <h2 id="audit-goal-title" class="text-2xl font-medium tracking-tight text-balance">What would you like more of?</h2>
                                        <p class="max-w-[56ch] text-pretty text-base text-ink/65">Optional. Help Ross focus on what matters to your business.</p>
                                        <form method="POST" action="{{ $goalUrl }}" class="flex flex-wrap gap-2">
                                            @csrf
                                            @method('PATCH')
                                            @foreach (['enquiries' => 'Enquiries', 'bookings' => 'Bookings', 'sales' => 'Sales'] as $value => $label)
                                                <button type="submit" name="customer_goal" value="{{ $value }}" aria-pressed="{{ $audit->customer_goal === $value ? 'true' : 'false' }}" @class(['inline-flex min-h-12 items-center justify-center rounded-full px-5 py-3 text-base font-medium ring-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden', 'bg-garden/10 text-garden ring-garden hover:bg-garden/15' => $audit->customer_goal === $value, 'bg-white text-ink ring-ink/15 hover:bg-lichen' => $audit->customer_goal !== $value])>{{ $label }}</button>
                                            @endforeach
                                        </form>
                                        @if (session('audit_goal_status')) <p role="status" class="text-base text-emerald-800">{{ session('audit_goal_status') }}</p> @endif
                                        @error('customer_goal') <p role="alert" class="text-base text-rose-700">{{ $message }}</p> @enderror
                                    </section>
                                @endif
                            </div>
                        @endif

                    </div>
                </div>
                </div>
            </div>
        @endif
        <p class="text-pretty text-base text-ink/50 sm:text-sm">This private link expires {{ $audit->expires_at->diffForHumans() }}.</p>
    </div>
</section>
@if ($audit->isReadyToDisplay() && $showDetails && ! $isFinalisingReport)
    <div data-audit-actions class="fixed right-4 bottom-4 z-40 sm:right-6 sm:bottom-6">
        <a data-audit-book-call href="{{ route('marketing.ppc.book') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-white py-2 pr-4 pl-2 text-base font-medium text-ink shadow-md ring-1 ring-ink/10 hover:bg-lichen focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden"><img src="{{ asset('ross-topping.jpg') }}" alt="" width="32" height="32" class="size-8 rounded-full object-cover"><span>Talk to Ross</span></a>
    </div>
@endif
@endsection
