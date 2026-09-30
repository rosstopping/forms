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
    $seo = data_get($audit->insights, 'seo');
    $projection = data_get($audit->insights, 'projection');
@endphp
<section data-marketing-events="{{ json_encode($marketingEvents) }}" class="px-3 pt-1 pb-16 sm:px-6 sm:pt-2 sm:pb-24" aria-labelledby="audit-title">
    <div class="mx-auto grid max-w-7xl gap-8 rounded-3xl bg-lichen px-5 py-10 sm:px-10 sm:py-14">
        <header class="grid gap-3">
            <p class="text-base font-medium text-garden sm:text-sm">Your website review</p>
            <h1 id="audit-title" class="max-w-[24ch] break-words text-4xl font-medium tracking-tight text-balance sm:text-5xl">{{ $audit->domain }}</h1>
            <p class="max-w-[56ch] text-pretty text-base text-ink/65">{{ $audit->isReadyToDisplay() ? 'Your website checks are ready.' : 'We’re checking your website.' }}</p>
        </header>

        @if ($audit->status !== \App\Models\WebsiteAudit::STATUS_FAILED && ! $audit->isReadyToDisplay())
            <div id="audit-progress" data-status-url="{{ route('marketing.website-audits.status', $audit) }}" class="border-t border-ink/10 pt-8" aria-live="polite">
                <p class="text-pretty text-base text-ink/65">Reviewing your website. This page will update when the checks are ready.</p>
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
                <p class="max-w-[56ch] text-pretty text-base text-ink/65">It may be unavailable or blocking automated checks. Check the address and try again.</p>
                <p><a href="{{ route('marketing.free-site-audit') }}" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Try another website address</a></p>
            </div>
        @else
            <div class="grid gap-10 border-t border-ink/10 pt-8">
                <section class="grid gap-6" aria-labelledby="audit-numbers-title">
                    <div class="grid gap-2">
                        <h2 id="audit-numbers-title" class="max-w-[35ch] text-3xl font-medium tracking-tight text-balance">What we found.</h2>
                        <p class="max-w-[56ch] text-pretty text-base text-ink/65">The health score covers checked website basics, not search rankings. Search and backlink figures are third-party estimates when available.</p>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-7 lg:grid-cols-4">
                        <div class="grid content-start gap-1 border-t border-ink/10 pt-4">
                            <dt class="text-base text-ink/65 sm:text-sm">Technical health</dt>
                            <dd class="order-first text-4xl font-medium tracking-tight tabular-nums text-ink sm:text-5xl">{{ $healthScore !== null ? $healthScore.'%' : '—' }}</dd>
                        </div>
                        <div class="grid content-start gap-1 border-t border-ink/10 pt-4">
                            <dt class="text-base text-ink/65 sm:text-sm">Pages listed in sitemap</dt>
                            <dd class="order-first text-4xl font-medium tracking-tight tabular-nums text-ink sm:text-5xl">{{ $pagesListed !== null ? number_format($pagesListed).($pagesPartial ? '+' : '') : '—' }}</dd>
                        </div>
                        <div class="grid content-start gap-1 border-t border-ink/10 pt-4">
                            <dt class="text-base text-ink/65 sm:text-sm">{{ \Illuminate\Support\Str::plural('Website fix', $fixCount) }} flagged</dt>
                            <dd data-audit-fix-count class="order-first text-4xl font-medium tracking-tight tabular-nums text-ink sm:text-5xl">{{ $fixCount }}</dd>
                        </div>
                        @if ($seo !== null)
                            <div class="grid content-start gap-1 border-t border-ink/10 pt-4">
                                <dt class="text-base text-ink/65 sm:text-sm">Google ranking terms</dt>
                                <dd class="order-first text-4xl font-medium tracking-tight tabular-nums text-ink sm:text-5xl">{{ number_format($seo['organic_keywords']) }}</dd>
                            </div>
                            <div class="grid content-start gap-1 border-t border-ink/10 pt-4">
                                <dt class="text-base text-ink/65 sm:text-sm">Terms in the top 3</dt>
                                <dd class="order-first text-4xl font-medium tracking-tight tabular-nums text-ink sm:text-5xl">{{ number_format($seo['top_3_keywords']) }}</dd>
                            </div>
                            <div class="grid content-start gap-1 border-t border-ink/10 pt-4">
                                <dt class="text-base text-ink/65 sm:text-sm">Terms in the top 10</dt>
                                <dd class="order-first text-4xl font-medium tracking-tight tabular-nums text-ink sm:text-5xl">{{ number_format($seo['top_10_keywords']) }}</dd>
                            </div>
                            <div class="grid content-start gap-1 border-t border-ink/10 pt-4">
                                <dt class="text-base text-ink/65 sm:text-sm">Est. monthly organic visits</dt>
                                <dd class="order-first text-4xl font-medium tracking-tight tabular-nums text-ink sm:text-5xl">{{ number_format($seo['estimated_monthly_visits']) }}</dd>
                            </div>
                            @if ($seo['referring_domains'] !== null)
                                <div class="grid content-start gap-1 border-t border-ink/10 pt-4">
                                    <dt class="text-base text-ink/65 sm:text-sm">Referring domains</dt>
                                    <dd class="order-first text-4xl font-medium tracking-tight tabular-nums text-ink sm:text-5xl">{{ number_format($seo['referring_domains']) }}</dd>
                                </div>
                            @endif
                        @endif
                    </dl>
                    @if ($pagesListed === null || $seo === null)
                        <p class="text-pretty text-base text-ink/55 sm:text-sm">{{ $seo === null ? 'Search estimates are unavailable for this review.' : '' }} {{ $pagesListed === null ? 'A page count needs an accessible XML sitemap.' : '' }}</p>
                    @endif
                </section>

                @if ($seo !== null)
                    <section class="grid gap-5 border-t border-ink/10 pt-8" aria-labelledby="audit-search-title">
                        <div class="grid gap-2">
                            <h2 id="audit-search-title" class="max-w-[35ch] text-2xl font-medium tracking-tight text-balance">Search snapshot.</h2>
                            <p class="max-w-[56ch] text-pretty text-base text-ink/65">DataForSEO estimates for Google in {{ $seo['location_code'] === 2826 ? 'the UK' : 'the selected market' }}. Monthly search volume is demand for a term, not visits to your site.</p>
                        </div>
                        @if ($seo['keywords'] !== [])
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-md border-collapse text-left text-base sm:text-sm">
                                    <thead class="border-b border-ink/15 text-ink/60">
                                        <tr><th scope="col" class="py-3 pr-4 font-medium">Ranking search</th><th scope="col" class="px-4 py-3 text-right font-medium">Position</th><th scope="col" class="py-3 pl-4 text-right font-medium">Monthly searches</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach (array_slice($seo['keywords'], 0, 6) as $keyword)
                                            <tr class="border-b border-ink/10 last:border-0">
                                                <td class="py-3 pr-4 text-ink">{{ $keyword['term'] }}</td>
                                                <td class="px-4 py-3 text-right tabular-nums text-ink/65">{{ $keyword['position'] }}</td>
                                                <td class="py-3 pl-4 text-right tabular-nums text-ink/65">{{ $keyword['monthly_searches'] !== null ? number_format($keyword['monthly_searches']) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-pretty text-base text-ink/55 sm:text-sm">A sample of {{ $seo['sample_size'] }} ranking terms; volumes and traffic are third-party estimates.</p>
                        @else
                            <p class="text-pretty text-base text-ink/65">{{ $seo['organic_keywords'] === 0 ? 'No ranking terms were found in this dataset yet.' : 'Keyword details are unavailable for this review.' }}</p>
                        @endif
                    </section>
                @endif

                <section class="grid gap-3 border-t border-ink/10 pt-8" aria-labelledby="audit-projection-title">
                    <h2 id="audit-projection-title" class="max-w-[35ch] text-2xl font-medium tracking-tight text-balance">Six-month opportunity.</h2>
                    @if ($projection !== null)
                        <p class="text-3xl font-medium tracking-tight tabular-nums text-ink sm:text-4xl">{{ number_format($projection['six_month_low']) }}–{{ number_format($projection['six_month_high']) }}</p>
                        <p class="text-base text-ink/65">Estimated organic visits a month</p>
                        <p class="max-w-[56ch] text-pretty text-base text-ink/65">Current estimate: {{ number_format($projection['baseline_monthly_visits']) }} a month. {{ $projection['method'] }} This is a scenario if we improve those pages, not a forecast or guarantee.</p>
                    @else
                        <p class="max-w-[56ch] text-pretty text-base text-ink/65">There isn’t enough ranking and search-volume evidence for a useful numeric estimate yet. We’d set a baseline, fix the site and review progress over the first six months.</p>
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

                <div class="border-t border-ink/10 pt-8">
                    <a href="{{ route('marketing.ppc.book') }}" class="inline-flex min-h-12 items-center justify-center gap-3 rounded-full bg-garden py-3 pr-4 pl-5 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden">Book a call with Ross <span aria-hidden="true">↗</span></a>
                </div>
            </div>
        @endif
        <p class="text-pretty text-base text-ink/50 sm:text-sm">This private link expires {{ $audit->expires_at->diffForHumans() }}.</p>
    </div>
</section>
@endsection
