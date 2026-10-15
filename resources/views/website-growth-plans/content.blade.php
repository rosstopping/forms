@php
    $report = $plan->report;
    $preparedAt = \Illuminate\Support\Carbon::parse($report['generated_at']);
@endphp
<main class="isolate mx-auto max-w-5xl px-4 py-6 sm:px-8 sm:py-10 print:max-w-none print:p-0" data-growth-plan-document>
    <header class="flex flex-wrap items-center justify-between gap-3 px-1 pb-6 print:pb-4">
        <p class="text-2xl font-semibold tracking-tight">sitewell<span class="text-garden">.</span></p>
        <p class="text-base text-ink/55 sm:text-sm">{{ $preparedAt->format('F Y') }} · Website growth plan</p>
    </header>

    <section class="grid gap-6 rounded-3xl bg-lichen p-6 sm:grid-cols-[3fr_2fr] sm:gap-8 sm:p-9 print:break-inside-avoid print:grid-cols-[3fr_2fr] print:p-6" aria-labelledby="plan-title">
        <div class="grid content-start gap-4">
            <p class="break-words text-base font-medium text-garden sm:text-sm">{{ $report['website_name'] }}</p>
            <h1 id="plan-title" class="max-w-[20ch] text-4xl font-medium tracking-tight text-balance sm:text-5xl print:text-4xl">{{ $document['headline'] }}</h1>
            @include('website-growth-plans.hero-copy', ['copyKey' => 'intro', 'copyTitle' => 'The plan for '.$report['website_name'], 'copyClass' => 'max-w-[48ch] text-lg'])
            <p class="break-all text-base text-ink/50 sm:text-sm">{{ $report['domain'] }}</p>
        </div>
        <div class="grid content-between gap-8 border-t border-ink/10 pt-5 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-8 print:border-t-0 print:border-l print:pt-0 print:pl-6">
            <div class="grid gap-2"><p class="text-base font-medium text-garden sm:text-sm">The next six months</p><p class="text-3xl font-medium tracking-tight text-balance">Better visibility.<br>More opportunity.</p></div>
            <div class="grid gap-2"><h2 class="text-base font-medium text-ink">What success looks like</h2>@include('website-growth-plans.hero-copy', ['copyKey' => 'goal', 'copyTitle' => 'What success looks like', 'copyClass' => 'max-w-[36ch] text-base'])</div>
        </div>
    </section>

    <section class="@container py-7 sm:py-9 print:py-5 print:break-inside-avoid" aria-label="Your starting point">
        <dl class="grid grid-cols-3 gap-4 @lg:gap-8">
            @foreach(['In focus' => [$document['focus_count'], 'Priority searches selected for this plan.'], 'Top 10' => [$document['top_ten_count'], 'Focus searches already in Google’s top 10.'], 'Checks flagged' => [$document['flagged_checks'], 'Audit warnings and failed checks to review.']] as $label => [$value, $description])
                <div class="grid gap-1 border-t border-ink/10 pt-3"><dt class="truncate text-base font-medium text-ink/65 sm:text-sm">{{ $label }}</dt><dd class="text-3xl font-medium tracking-tight tabular-nums text-ink sm:text-4xl">{{ $value !== null ? number_format($value) : '—' }}</dd><dd class="text-base text-pretty text-ink/60 sm:text-sm">{{ $description }}</dd></div>
            @endforeach
        </dl>
        <p class="mt-3 text-base text-pretty text-ink/50 sm:text-sm">Search figures cover this shortlist.@if($document['health_date']) Checks from {{ \Illuminate\Support\Carbon::parse($document['health_date'])->format('j M Y') }}.@endif</p>
    </section>

    @if($document['priorities'])
        <section class="grid gap-5 pb-8 sm:pb-10 print:break-inside-avoid print:pb-6" aria-labelledby="priorities-title">
            <div class="grid gap-1"><p class="text-base font-medium text-garden sm:text-sm">The important work</p><h2 id="priorities-title" class="text-3xl font-medium tracking-tight text-balance">{{ count($document['priorities']) === 3 ? 'Three clear priorities.' : 'The priorities.' }}</h2></div>
            <dl class="grid gap-6 sm:grid-cols-3 print:grid-cols-3">
                @foreach($document['priorities'] as $priority)
                    <div class="grid content-start gap-3 border-t border-ink/10 pt-4" data-plan-priority>
                        <dt class="grid gap-3"><p class="font-mono text-base text-garden tabular-nums sm:text-sm">0{{ $loop->iteration }}</p><h3 class="text-xl font-medium text-balance">{{ $priority['title'] }}</h3></dt>
                        <dd class="grid gap-3"><p class="text-base text-pretty text-ink/65 sm:text-sm">{{ $priority['action'] }}</p>@if($priority['outcome'])<p class="text-base font-medium text-pretty text-ink sm:text-sm">{{ $priority['outcome'] }}</p>@endif</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    <section class="grid gap-5 border-t border-ink/10 py-7 sm:py-9 print:break-inside-avoid print:py-5" aria-labelledby="focus-title">
        <div class="grid gap-1"><p class="text-base font-medium text-garden sm:text-sm">Where we want to win</p><h2 id="focus-title" class="text-3xl font-medium tracking-tight text-balance">The searches that matter.</h2></div>
        @if($document['keywords'])
            <table class="w-full table-fixed border-collapse text-left text-base sm:text-sm">
                <caption class="sr-only">Focus keywords, recent saved rankings and indicative six-month aims</caption>
                <thead><tr class="border-b border-ink/10"><th scope="col" class="w-1/2 whitespace-nowrap pb-3 pr-3 font-medium text-ink/60">Search focus</th><th scope="col" class="w-1/6 whitespace-nowrap pb-3 text-right font-medium text-ink/60">Now</th><th scope="col" class="w-1/3 whitespace-nowrap pb-3 pl-3 text-right font-medium text-ink/60">6-month aim</th></tr></thead>
                <tbody>
                    @foreach($document['keywords'] as $keyword)
                        <tr class="border-b border-ink/10 last:border-0 print:break-inside-avoid" data-plan-keyword>
                            <th scope="row" class="break-words py-3 pr-3 font-medium text-ink">{{ $keyword['term'] }}@if($keyword['intended_url'])<p class="mt-1 break-all font-normal text-ink/50">{{ parse_url($keyword['intended_url'], PHP_URL_PATH) ?: '/' }}</p>@elseif($keyword['suggested'] ?? false)<p class="mt-1 font-normal text-ink/50">Suggested focus</p>@endif</th>
                            <td class="py-3 text-right tabular-nums text-ink/65">{{ $keyword['baseline'] ? ($keyword['baseline']['status'] === 'ranked' ? '#'.$keyword['baseline']['position'] : '>100') : '—' }}</td>
                            <td class="py-3 pl-3 text-right font-medium text-garden tabular-nums">{{ filled($keyword['target_best'] ?? null) ? '#'.$keyword['target_best'].'–#'.$keyword['target_worst'] : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-base text-pretty text-ink/65">Establish the keyword baseline first, then focus the work on the most relevant searches.</p>
        @endif
        <p class="max-w-[72ch] text-base text-pretty text-ink/50 sm:text-sm">Illustrative aims, not predicted or guaranteed rankings. — means more evidence is needed. “Now” uses saved desktop estimates, not Search Console averages.</p>
    </section>

    <section class="grid gap-6 rounded-3xl bg-lichen p-6 sm:p-8 print:break-inside-avoid print:p-6" aria-labelledby="roadmap-title">
        <div class="grid gap-1"><p class="text-base font-medium text-garden sm:text-sm">A clear order of work</p><h2 id="roadmap-title" class="text-3xl font-medium tracking-tight text-balance">Your six-month roadmap.</h2></div>
        <ol role="list" class="grid gap-x-7 gap-y-6 sm:grid-cols-3 print:grid-cols-3">
            @foreach($document['roadmap'] as $month => $action)
                <li class="grid content-start gap-2 border-t border-ink/10 pt-4 print:break-inside-avoid" data-plan-milestone><p class="font-mono text-base font-medium text-garden sm:text-sm">Month {{ $month + 1 }}</p><p class="text-base text-pretty text-ink/75 sm:text-sm">{{ $action }}</p></li>
            @endforeach
        </ol>
    </section>

    @if($document['dependencies'])
        <section class="grid gap-2 border-b border-ink/10 py-6 print:break-inside-avoid print:py-4" aria-labelledby="support-title"><h2 id="support-title" class="text-lg font-medium text-balance">What we need from you.</h2><p class="max-w-[72ch] text-base text-pretty text-ink/65 sm:text-sm">{{ $document['dependencies'] }}</p></section>
    @endif
    <footer class="grid gap-2 pt-5 text-base text-ink/50 sm:text-sm print:break-inside-avoid">
        <p>Prepared for {{ $report['website_name'] }} · {{ $preparedAt->format('j F Y') }}.</p>
        @if($document['rank_dates'])<p>Saved ranking checks: {{ \Illuminate\Support\Carbon::parse($document['rank_dates'][0])->format('j M Y') }}@if($document['rank_dates'][0] !== $document['rank_dates'][1])–{{ \Illuminate\Support\Carbon::parse($document['rank_dates'][1])->format('j M Y') }}@endif. Lower positions rank higher.</p>@endif
        @if($document['limitations'])<p class="max-w-[88ch] text-pretty">{{ $document['limitations'] }}</p>@endif
    </footer>
</main>
