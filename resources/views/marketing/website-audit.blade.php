@extends('layouts.marketing')

@section('title', 'Website review for '.$audit->domain)
@section('meta_description', 'Your private Sitewell website review and proposed next steps.')

@section('content')
@php
    $findings = collect($audit->findings ?? []);
    $fixCount = $findings->whereIn('severity', ['warning', 'failed'])->count();
    $healthScore = data_get($audit->insights, 'health_score');
    $healthScore ??= $findings->isNotEmpty() ? (int) round($findings->where('severity', 'passed')->count() / $findings->count() * 100) : null;
    $pagesListed = data_get($audit->insights, 'pages_listed');
    $pagesPartial = (bool) data_get($audit->insights, 'pages_partial', false);
    $pagesMismatchedDomain = (int) data_get($audit->insights, 'pages_mismatched_domain', 0);
    $pagesMismatchedHost = data_get($audit->insights, 'pages_mismatched_host');
    $seo = data_get($audit->insights, 'seo');
    $competitors = data_get($audit->insights, 'competitors');
    $aiVisibility = data_get($audit->insights, 'ai_visibility');
@endphp
<section data-marketing-events="{{ json_encode($marketingEvents) }}" class="px-3 pt-1 pb-16 sm:px-6 sm:pt-2 sm:pb-24" aria-labelledby="audit-title">
    <div class="mx-auto grid max-w-7xl gap-8 rounded-3xl bg-lichen px-5 py-10 sm:px-10 sm:py-14">
        <header class="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start lg:gap-10">
            <div class="grid min-w-0 content-start gap-3">
                <p class="text-base font-medium text-garden sm:text-sm">Your website review</p>
                <h1 id="audit-title" class="w-full min-w-0 max-w-[24ch] truncate text-4xl font-medium tracking-tight sm:text-5xl" title="{{ $audit->domain }}">{{ $audit->domain }}</h1>
                @if ($audit->isReadyToDisplay())
                    <p class="max-w-[56ch] text-pretty text-base text-ink/65">Your website checks are ready.</p>
                @endif
            </div>
            @if ($screenshotUrl)
                <figure class="max-w-sm rounded-[min(1.5vw,18px)] bg-white p-1.5 ring-1 ring-ink/10 lg:justify-self-end">
                    <img src="{{ $screenshotUrl }}" alt="" width="1024" height="640" class="aspect-[8/5] w-full rounded-[min(1vw,12px)] object-cover object-top outline-1 -outline-offset-1 outline-black/5" loading="eager" decoding="async">
                </figure>
            @endif
        </header>

        @if (session('report_email_status'))
            <p role="status" class="rounded-2xl bg-emerald-50 px-5 py-4 text-base text-emerald-900 ring-1 ring-emerald-200/70">{{ session('report_email_status') }}</p>
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
                        <h2 class="text-2xl font-medium tracking-tight text-balance sm:text-3xl">Building your audit.</h2>
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
                <p><a href="{{ route('marketing.ppc.book') }}" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Book a call with Ross for help reviewing your website</a></p>
            </div>
        @else
            <div class="grid gap-10 border-t border-ink/10 pt-8">
                <section class="grid gap-6" aria-labelledby="audit-numbers-title">
                    <div class="grid gap-2">
                        <h2 id="audit-numbers-title" class="max-w-[35ch] text-3xl font-medium tracking-tight text-balance">What we found.</h2>
                        <p class="max-w-[56ch] text-pretty text-base text-ink/65">A quick look at your website checks and search visibility.</p>
                    </div>
                    <dl class="grid gap-3 md:grid-cols-3">
                        <div @class([
                            'grid content-start gap-3 rounded-2xl p-5 ring-1 sm:p-6',
                            'bg-emerald-50 ring-emerald-200/70' => $healthScore !== null && $healthScore >= 80,
                            'bg-amber-50 ring-amber-200/70' => $healthScore !== null && $healthScore >= 50 && $healthScore < 80,
                            'bg-rose-50 ring-rose-200/70' => $healthScore !== null && $healthScore < 50,
                            'bg-white ring-ink/10' => $healthScore === null,
                        ])>
                            <dt class="text-base text-ink/65 sm:text-sm">Technical health</dt>
                            <dd class="text-5xl font-medium tracking-tight tabular-nums text-ink">{{ $healthScore !== null ? $healthScore.'%' : '—' }}</dd>
                            <dd @class([
                                'text-base font-medium sm:text-sm',
                                'text-emerald-800' => $healthScore !== null && $healthScore >= 80,
                                'text-amber-900' => $healthScore !== null && $healthScore >= 50 && $healthScore < 80,
                                'text-rose-800' => $healthScore !== null && $healthScore < 50,
                                'text-ink/60' => $healthScore === null,
                            ])>{{ $healthScore === null ? 'No score available' : ($healthScore >= 80 ? 'Looking healthy' : ($healthScore >= 50 ? 'Needs some work' : 'Needs attention')) }}</dd>
                            @if ($healthScore !== null)
                                <div class="h-1.5 overflow-hidden rounded-full bg-ink/10" aria-hidden="true"><div @class(['h-full rounded-full', 'bg-emerald-600' => $healthScore >= 80, 'bg-amber-500' => $healthScore >= 50 && $healthScore < 80, 'bg-rose-600' => $healthScore < 50]) style="width: {{ $healthScore }}%"></div></div>
                            @endif
                        </div>
                        <div @class(['grid content-start gap-3 rounded-2xl p-5 ring-1 sm:p-6', 'bg-amber-50 ring-amber-200/70' => $fixCount > 0, 'bg-emerald-50 ring-emerald-200/70' => $fixCount === 0])>
                            <dt class="text-base text-ink/65 sm:text-sm">{{ \Illuminate\Support\Str::plural('Website fix', $fixCount) }} flagged</dt>
                            <dd data-audit-fix-count class="text-5xl font-medium tracking-tight tabular-nums text-ink">{{ $fixCount }}</dd>
                            <dd @class(['text-base font-medium sm:text-sm', 'text-amber-900' => $fixCount > 0, 'text-emerald-800' => $fixCount === 0])>{{ $fixCount > 0 ? 'Worth fixing' : 'No fixes flagged' }}</dd>
                        </div>
                        @if ($seo !== null)
                            <div @class(['grid content-start gap-3 rounded-2xl p-5 ring-1 sm:p-6', 'bg-amber-50 ring-amber-200/70' => $seo['top_10_keywords'] === 0, 'bg-emerald-50 ring-emerald-200/70' => $seo['top_10_keywords'] > 0])>
                                <dt class="text-base text-ink/65 sm:text-sm">Google terms in the top 10</dt>
                                <dd class="text-5xl font-medium tracking-tight tabular-nums text-ink">{{ number_format($seo['top_10_keywords']) }}</dd>
                                <dd @class(['text-base font-medium sm:text-sm', 'text-amber-900' => $seo['top_10_keywords'] === 0, 'text-emerald-800' => $seo['top_10_keywords'] > 0])>{{ $seo['top_10_keywords'] === 0 ? 'No page-one terms yet' : 'Visible on page one' }}</dd>
                            </div>
                        @else
                            <div class="grid content-start gap-3 rounded-2xl bg-white p-5 ring-1 ring-ink/10 sm:p-6">
                                <dt class="text-base text-ink/65 sm:text-sm">URLs in sitemap</dt>
                                <dd class="text-5xl font-medium tracking-tight tabular-nums text-ink">{{ $pagesListed !== null ? number_format($pagesListed).($pagesPartial ? '+' : '') : '—' }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if ($seo !== null)
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-6 sm:grid-cols-3 lg:grid-cols-5">
                            <div class="grid content-start gap-1 border-t border-ink/10 pt-4"><dt class="text-base text-ink/65 sm:text-sm">URLs in sitemap</dt><dd class="order-first text-3xl font-medium tracking-tight tabular-nums text-ink">{{ $pagesListed !== null ? number_format($pagesListed).($pagesPartial ? '+' : '') : '—' }}</dd></div>
                            <div class="grid content-start gap-1 border-t border-ink/10 pt-4"><dt class="text-base text-ink/65 sm:text-sm">Google ranking terms</dt><dd class="order-first text-3xl font-medium tracking-tight tabular-nums text-ink">{{ number_format($seo['organic_keywords']) }}</dd></div>
                            <div class="grid content-start gap-1 border-t border-ink/10 pt-4"><dt class="text-base text-ink/65 sm:text-sm">Terms in the top 3</dt><dd class="order-first text-3xl font-medium tracking-tight tabular-nums text-ink">{{ number_format($seo['top_3_keywords']) }}</dd></div>
                            <div class="grid content-start gap-1 border-t border-ink/10 pt-4"><dt class="text-base text-ink/65 sm:text-sm">Est. monthly organic visits</dt><dd class="order-first text-3xl font-medium tracking-tight tabular-nums text-ink">{{ number_format($seo['estimated_monthly_visits']) }}</dd></div>
                            @if ($seo['referring_domains'] !== null)
                                <div class="grid content-start gap-1 border-t border-ink/10 pt-4"><dt class="text-base text-ink/65 sm:text-sm">Referring domains</dt><dd class="order-first text-3xl font-medium tracking-tight tabular-nums text-ink">{{ number_format($seo['referring_domains']) }}</dd></div>
                            @endif
                        </dl>
                    @endif
                    @if ($pagesMismatchedDomain > 0)
                        <p class="rounded-2xl bg-amber-50 p-4 text-base text-amber-900 ring-1 ring-amber-200/70 sm:text-sm">Sitemap issue: {{ number_format($pagesMismatchedDomain) }} {{ $pagesMismatchedDomain === 1 ? 'URL points' : 'URLs point' }} {{ $pagesMismatchedHost ? 'to '.$pagesMismatchedHost.' instead of '.$audit->domain : 'away from '.$audit->domain }}. Update the sitemap to use your live domain.</p>
                    @endif
                    <p class="text-pretty text-base text-ink/55 sm:text-sm">Technical health covers the checks we ran. Search and backlink figures are third-party estimates.</p>
                    @if ($pagesListed === null || $seo === null)
                        <p class="text-pretty text-base text-ink/55 sm:text-sm">{{ $seo === null ? 'Search estimates are unavailable for this review.' : '' }} {{ $pagesListed === null ? 'A page count needs an accessible XML sitemap.' : '' }}</p>
                    @endif
                </section>
                @if ($audit->report_requested_at === null)
                    <section aria-labelledby="audit-review-title" class="rounded-3xl bg-black p-6 text-white ring-1 ring-black sm:p-8">
                        <div class="flex items-start gap-4 sm:gap-5">
                            <img data-audit-review-portrait src="{{ asset('ross-topping.jpg') }}" alt="Ross" width="56" height="56" class="size-14 shrink-0 rounded-full object-cover outline-1 -outline-offset-1 outline-white/10">
                            <div data-audit-review-copy class="grid min-w-0 flex-1 gap-4">
                                <h2 id="audit-review-title" class="max-w-[40ch] text-3xl font-medium tracking-tight text-balance">Want to know what to fix first?</h2>
                                <p class="max-w-[56ch] text-pretty text-base text-white/75">I’ll review your results and email you a personal video walking through what I’d improve and where I’d start.</p>
                                <div><button type="button" data-audit-email-open aria-haspopup="dialog" aria-controls="audit-email-dialog" class="inline-flex min-h-12 items-center justify-center rounded-full bg-garden px-5 py-3 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden">Get Ross’s recommendations</button></div>
                            </div>
                        </div>
                    </section>
                @elseif ($audit->personal_review_requested_at && ! $audit->personal_review_queued_at)
                    <section role="status" aria-labelledby="audit-request-received-title" class="flex items-start gap-4 rounded-3xl bg-black p-6 text-white ring-1 ring-black sm:p-8">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-emerald-400/15 text-emerald-300" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                        </span>
                        <div class="grid gap-2">
                            <h2 id="audit-request-received-title" class="text-2xl font-medium tracking-tight sm:text-3xl">Request received</h2>
                            <p class="max-w-[60ch] text-base text-white/80">Ross has your request and will be in touch via email shortly.</p>
                        </div>
                    </section>
                @endif


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

                @if ($seo !== null)
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
                @if (is_array($aiVisibility))
                    @if ($aiVisibility['status'] === 'pending')
                        <script>
                            (() => {
                                const statusUrl = @js(route('marketing.website-audits.status', $audit));
                                const timer = window.setInterval(async () => {
                                    try {
                                        const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                                        if (! response.ok) return;
                                        if ((await response.json()).ai_visibility_status !== 'pending') {
                                            window.clearInterval(timer);
                                            window.location.reload();
                                        }
                                    } catch (_) {}
                                }, 5000);
                            })();
                        </script>
                    @endif
                @endif

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
                        <p class="max-w-[56ch] text-pretty text-base text-ink/65">This range assumes we improve pages already ranking just outside page one. It is an illustration, not a guarantee.</p>
                        <details class="group border-t border-ink/10 pt-5">
                            <summary class="cursor-pointer font-medium text-garden marker:text-garden">How we estimated this</summary>
                            <p class="max-w-[65ch] pt-3 text-pretty text-base text-ink/65 sm:text-sm">{{ $projection['method'] }} Search volumes and the current visit count come from third-party estimates.</p>
                        </details>
                    @else
                        <p class="max-w-[56ch] text-pretty text-base text-ink/65">There isn’t enough ranking data for a useful estimate yet. We’d set a baseline, improve the site and review progress over the first six months.</p>
                    @endif
                </section>

                <div data-audit-next-step class="grid gap-6 border-t border-ink/10 pt-8" aria-labelledby="audit-plan-title">
                    <h2 id="audit-plan-title" class="max-w-[35ch] text-3xl font-medium tracking-tight text-balance">What we’d do next.</h2>
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

                <section data-audit-call-section class="grid gap-6 rounded-3xl bg-ink p-6 text-white sm:p-8 md:grid-cols-[minmax(0,1fr)_auto] md:items-center" aria-labelledby="audit-call-title">
                    <div class="grid gap-2">
                        <h2 id="audit-call-title" class="text-3xl font-medium tracking-tight text-balance">Talk through your audit with Ross.</h2>
                        <p class="max-w-[52ch] text-base text-white/75">We’ll explain the findings and what we’d work on first.</p>
                    </div>
                    <a href="{{ route('marketing.ppc.book') }}" class="inline-flex min-h-12 items-center justify-center gap-3 rounded-full bg-garden px-5 py-3 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white md:justify-self-end">Book a call with Ross <span aria-hidden="true">↗</span></a>
                </section>
            </div>
        @endif
        <p class="text-pretty text-base text-ink/50 sm:text-sm">This private link expires {{ $audit->expires_at->diffForHumans() }}.</p>
    </div>
</section>
@if ($audit->isReadyToDisplay())
    <div data-audit-actions class="fixed right-4 bottom-4 left-4 z-40 flex justify-end gap-2 sm:right-6 sm:bottom-6 sm:left-auto">
        @if ($audit->report_requested_at === null)
            <button type="button" data-audit-email-open aria-label="Get Ross’s recommendations" aria-haspopup="dialog" aria-controls="audit-email-dialog" class="inline-flex min-h-12 items-center justify-center rounded-full bg-garden px-4 py-3 text-sm font-medium text-white shadow-md hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden"><span class="sm:hidden">Get my next steps</span><span class="max-sm:hidden">Get Ross’s recommendations</span></button>
        @endif
        <a data-audit-book-call href="{{ route('marketing.ppc.book') }}" aria-label="Book a call with Ross" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-white py-2 pr-4 pl-2 text-sm font-medium text-garden shadow-md ring-1 ring-ink/10 hover:bg-lichen focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden"><img src="{{ asset('ross-topping.jpg') }}" alt="" width="32" height="32" class="size-8 shrink-0 rounded-full object-cover"><span class="sm:hidden">Book a call</span><span class="max-sm:hidden">Book a call with Ross</span></a>
    </div>
@endif
@if ($audit->isReadyToDisplay() && $audit->report_requested_at === null)
    <dialog id="audit-email-dialog" aria-labelledby="audit-email-title" aria-describedby="audit-email-description" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto border-0 bg-transparent p-0 pt-10 text-ink backdrop:bg-ink/60">
        <div class="relative rounded-3xl bg-white p-6 pt-14 sm:p-8 sm:pt-14">
            <img data-audit-review-portrait src="{{ asset('ross-topping.jpg') }}" alt="Ross" width="72" height="72" class="absolute top-0 left-1/2 size-18 -translate-x-1/2 -translate-y-1/2 rounded-full object-cover ring-4 ring-white">
            <button type="button" data-audit-email-close aria-label="Close email prompt" class="absolute top-3 right-3 grid size-12 place-items-center rounded-full text-xl text-ink/60 hover:bg-lichen focus-visible:outline-2 focus-visible:outline-garden">×</button>
            <div class="grid gap-4">
                <h2 id="audit-email-title" class="max-w-[40ch] text-3xl font-medium tracking-tight text-balance">Want to know what to fix first?</h2>
                <p id="audit-email-description" class="max-w-[56ch] text-pretty text-base text-ink/65">I’ll personally review your results and email you a video with practical next steps within one working day. You’ll also get your report link, available for 14 days.</p>
            </div>
            <form method="POST" action="{{ route('marketing.website-audits.email-report', $audit) }}" class="grid gap-4 pt-6">
                @csrf
                <input type="hidden" name="personal_review" value="1">
                <div class="absolute -left-[9999px]" aria-hidden="true"><label for="audit-email-check">Leave this blank</label><input id="audit-email-check" type="text" name="_sitewell_check" tabindex="-1" autocomplete="off"></div>
                <div class="grid gap-2">
                    <label for="audit-report-email" class="text-base font-medium text-ink sm:text-sm">Email address</label>
                    <input id="audit-report-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required maxlength="255" autofocus class="min-h-12 w-full rounded-xl bg-white px-4 text-base text-ink ring-1 ring-ink/15 outline-none placeholder:text-ink/40 focus-visible:ring-2 focus-visible:ring-garden" placeholder="you@example.com">
                    @error('email') <p class="text-base text-rose-700 sm:text-sm">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-start gap-3 text-base text-ink/65 sm:text-sm">
                    <input type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent')) class="mt-1 size-5 shrink-0 accent-garden">
                    <span>{{ \App\Models\WebsiteAudit::MARKETING_CONSENT_TEXT }}</span>
                </label>
                @error('marketing_consent') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                <p class="text-base text-ink/60 sm:text-sm">No obligation to book a call. <a href="{{ route('marketing.privacy') }}" class="underline underline-offset-4">Privacy policy</a></p>
                <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-full bg-garden px-4 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden">Get Ross’s recommendations</button>
            </form>
        </div>
    </dialog>
    <script>
        (() => {
            const dialog = document.getElementById('audit-email-dialog');
            if (! dialog?.showModal) return;

            const storageKey = @js('sitewell-audit-email-dismissed:'.$audit->public_id);
            const hasError = @js($errors->any());
            let promptTimer;
            const open = () => {
                if (document.visibilityState === 'visible' && ! dialog.open) dialog.showModal();
            };

            document.querySelectorAll('[data-audit-email-open]').forEach(button => button.addEventListener('click', () => {
                window.clearTimeout(promptTimer);
                open();
            }));
            dialog.querySelector('[data-audit-email-close]').addEventListener('click', () => dialog.close());
            dialog.addEventListener('close', () => {
                try { sessionStorage.setItem(storageKey, '1'); } catch (_) {}
            });

            if (hasError) {
                open();
                return;
            }

            try { if (sessionStorage.getItem(storageKey) === '1') return; } catch (_) {}

            promptTimer = window.setTimeout(() => {
                if (document.visibilityState === 'visible') {
                    open();
                } else {
                    const openWhenVisible = () => {
                        if (document.visibilityState !== 'visible') return;
                        document.removeEventListener('visibilitychange', openWhenVisible);
                        open();
                    };
                    document.addEventListener('visibilitychange', openWhenVisible);
                }
            }, 30000);
        })();
    </script>
@endif
@endsection
