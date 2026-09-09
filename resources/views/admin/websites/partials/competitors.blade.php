<div class="space-y-6">
    <section class="rounded-xl border bg-white p-4 shadow-sm sm:p-6" aria-labelledby="competitor-research-heading">
        <h2 id="competitor-research-heading" class="text-lg font-semibold text-slate-950">Competitor research</h2>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">Learn which searches competitors appear for and how their pages address them. Audit selected domains to find evidence-backed content ideas. Audits use paid provider data, inspect up to five pages, and reuse results for seven days.</p>
        @if ($canManageWebsite)
            <form method="POST" action="{{ route('admin.competitors.store', $website) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1"><label for="competitor-domain" class="block text-sm font-medium text-slate-700">Add a competitor domain</label><input id="competitor-domain" name="domain" value="{{ old('domain') }}" placeholder="example.com" required maxlength="253" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"></div>
                <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Add competitor</button>
            </form>
            @error('domain')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        @endif
        <div class="mt-6 divide-y divide-slate-100">
            @forelse ($trackedCompetitors as $competitor)
                <article class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h3 class="break-all font-medium text-slate-950">{{ $competitor->domain }} @if ($competitor->excluded)<span class="text-xs text-slate-500">Excluded</span>@endif</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $competitor->latestAudit ? ucfirst(str_replace('_', ' ', $competitor->latestAudit->status)) : 'Not audited' }} · Last audited {{ $competitor->latestAudit?->completed_at?->format('j M Y, H:i') ?? '—' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        @if ($competitor->latestAudit)<a class="font-medium text-teal-700 hover:underline" href="{{ route('admin.competitor-audits.show', [$website, $competitor->latestAudit]) }}">View audit</a>@endif
                        @if ($canManageWebsite)
                            @unless ($competitor->excluded)
                                <form method="POST" action="{{ route('admin.competitors.audit', [$website, $competitor]) }}">@csrf<button class="rounded-md border border-slate-300 px-3 py-2 font-medium hover:bg-slate-50">Audit</button></form>
                            @endunless
                            <form method="POST" action="{{ route('admin.competitors.update', [$website, $competitor]) }}">@csrf @method('PUT')<input type="hidden" name="excluded" value="{{ $competitor->excluded ? '0' : '1' }}"><button class="text-slate-600 hover:underline">{{ $competitor->excluded ? 'Restore' : 'Exclude' }}</button></form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="py-6 text-sm text-slate-500">No competitors selected yet. Add a domain or select one from the discovered competitors below.</p>
            @endforelse
        </div>
    </section>
    <section class="rounded-xl border bg-white p-4 shadow-sm sm:p-6" aria-labelledby="organic-competitors-title">
        <h3 id="organic-competitors-title" class="font-semibold text-slate-950">Organic competitors</h3>
        <p class="mt-1 text-sm text-slate-600">Domains appearing alongside this website for the same organic searches. Third-party estimates from {{ $seoSnapshot?->completed_at?->format('j M Y') ?? 'the latest snapshot' }}; separate from Search Console.</p>
        @if (isset($seoSnapshot->errors['organic_competitors']))<p class="mt-3 text-sm text-amber-800">Organic competitor data was unavailable when this snapshot was generated. Other successful SEO data has been retained.</p>@endif
        <div class="mt-4 overflow-x-auto">
            <table class="w-full whitespace-nowrap text-sm">
                <thead><tr class="border-b text-left"><th class="p-3">Domain</th><th class="p-3 text-right">Shared keywords</th><th class="p-3 text-right">Ranking keywords</th><th class="p-3 text-right">Estimated visits</th><th class="p-3"><span class="sr-only">Select competitor</span></th></tr></thead>
                <tbody>
                    @forelse ($seoCompetitors as $discovery)
                        <tr class="border-b border-slate-100"><td class="p-3 font-medium">{{ $discovery->domain }}</td><td class="p-3 text-right">{{ number_format($discovery->common_keywords) }}</td><td class="p-3 text-right">{{ is_null($discovery->organic_keywords) ? '—' : number_format($discovery->organic_keywords) }}</td><td class="p-3 text-right">{{ is_null($discovery->estimated_traffic) ? '—' : '~'.number_format((float) $discovery->estimated_traffic) }}</td><td class="p-3">
                            @if ($canManageWebsite && ! $trackedCompetitors->contains('domain', $discovery->domain))<form method="POST" action="{{ route('admin.competitors.store', $website) }}">@csrf<input type="hidden" name="domain" value="{{ $discovery->domain }}"><button class="font-medium text-teal-700 hover:underline">Select</button></form>@endif
                        </td></tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-500">No organic competitors were returned for this snapshot.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
