@props(['reviews'])
@if ($reviews->isNotEmpty())
    <section class="ui-panel ui-section" aria-labelledby="impact-reviews-heading">
        <h2 id="impact-reviews-heading" class="text-xl font-semibold">SEO results ready to review</h2>
        <p class="mt-2 text-base text-slate-600 sm:text-sm">Sitewell has checked the work and prepared the next step. No report to fill in.</p>
        <div class="mt-4 divide-y divide-slate-950/10">
            @foreach ($reviews as $impact)
                <article class="flex flex-wrap items-start justify-between gap-4 py-4">
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold">{{ $impact->title }}</h3>
                        @if ($impact->relationLoaded('website'))
                            <p class="mt-1 text-sm text-slate-500">{{ $impact->website->name }}</p>
                        @endif
                        @if (in_array($impact->verification_status, ['attention', 'scope_missing']))
                            <p class="mt-2 text-base text-amber-800 sm:text-sm">The live page checks need attention. Open the result to see what could not be verified.</p>
                        @endif
                        <p class="mt-2 text-base text-slate-600 sm:text-sm">{{ $impact->automatic_summary }}</p>
                        @if ($impact->measurement_error)
                            <p class="mt-2 text-base text-amber-800 sm:text-sm">{{ $impact->measurement_error }}</p>
                        @endif
                    </div>
                    <a class="ui-button ui-button-secondary" href="{{ route('admin.websites.section', [$impact->website_id, 'seo', 'seo_section' => 'impact', 'seo_impact' => $impact->id]) }}">Review result</a>
                </article>
            @endforeach
        </div>
    </section>
@endif
