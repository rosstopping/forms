<figure class="min-w-0 rounded-xl bg-white p-5 shadow-xl shadow-ink/5 ring-1 ring-ink/10 sm:p-8">
    <figcaption class="flex flex-wrap items-center justify-between gap-3 border-b border-ink/10 pb-5">
        <span class="text-base font-medium">{{ ($preview ?? 'portfolio') === 'report' ? 'A clearer client update' : 'Your client portfolio' }}</span>
        <span class="rounded-md bg-lichen px-2.5 py-1 text-base text-moss sm:text-sm">Agency beta concept</span>
    </figcaption>
    @if (($preview ?? 'portfolio') === 'report')
        <div class="grid gap-6 pt-6">
            <div><p class="font-mono text-base text-garden sm:text-sm">YOUR AGENCY / WEEKLY UPDATE</p><p class="mt-2 font-display text-3xl font-semibold tracking-tight">Example Kitchen Studio</p><p class="mt-2 text-base text-ink/65">A short review of your website this week.</p></div>
            @foreach ([['What changed', 'Your kitchen fitting page appeared in more searches. Clicks were steady.'], ['Work completed', 'A broken link on the services page was corrected and checked.'], ['Your next decision', 'Please review the proposed description for your kitchen fitting page.']] as [$label, $copy])
                <div class="border-t border-ink/10 pt-4"><p class="text-base font-medium">{{ $label }}</p><p class="mt-2 max-w-[56ch] text-pretty text-base/7 text-ink/65">{{ $copy }}</p></div>
            @endforeach
        </div>
    @else
        <div class="flex flex-wrap items-end justify-between gap-4 py-6">
            <p class="font-display text-3xl font-semibold tracking-tight tabular-nums">32 <span class="font-sans text-base font-normal text-ink/65">client websites</span></p>
            <p class="text-base text-ink/65 sm:text-sm">Illustrative weekly overview</p>
        </div>
        <dl class="grid grid-cols-3 gap-4 border-y border-ink/10 py-5">
            @foreach ([['25', 'Healthy'], ['5', 'Opportunities'], ['2', 'Needs attention']] as [$value, $label])
                <div><dt class="text-base text-ink/65 sm:text-sm">{{ $label }}</dt><dd class="mt-2 text-2xl font-semibold tabular-nums">{{ $value }}</dd></div>
            @endforeach
        </dl>
        <ul role="list" class="divide-y divide-ink/10">
            @foreach ([['Example Kitchen Studio', 'Healthy', '3 keywords improved · 2 opportunities', 'Audit checked · Review service-page copy'], ['Example Plumbing Co.', 'Needs attention', 'Organic position 14 → 7 for one tracked term', 'Broken link resolved · Reconnect search data'], ['Example Roofing Studio', 'Opportunity', 'New topic: flat roof repair Doncaster', 'Audit checked · Content brief awaiting review']] as [$name, $state, $movement, $work])
                <li class="py-5"><div class="flex flex-wrap items-center justify-between gap-2"><p class="text-base font-medium">{{ $name }}</p><span class="text-base text-moss sm:text-sm">{{ $state }}</span></div><p class="mt-2 text-pretty text-base text-ink/70 sm:text-sm">{{ $movement }}</p><p class="mt-1 text-pretty text-base text-ink/55 sm:text-sm">{{ $work }}</p></li>
            @endforeach
        </ul>
    @endif
    <p class="mt-5 border-t border-ink/10 pt-4 text-pretty text-base/6 text-ink/60 sm:text-sm/6">Design preview with fictional businesses and data, not customer results. {{ ($preview ?? 'portfolio') === 'report' ? 'Agency-branded reporting is proposed; current reports use Sitewell branding.' : 'This consolidated agency dashboard is proposed; current customer access uses individual website workspaces.' }}</p>
</figure>
