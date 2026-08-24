@if ($website->searchConsoleConnection?->property_url)
<section class="rounded-lg border border-slate-200 bg-white shadow-sm" aria-labelledby="search-opportunities-title">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 p-4">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Weekly analysis</p>
            <h2 id="search-opportunities-title" class="mt-1 font-semibold text-slate-950">Search opportunities</h2>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">Directional opportunities from two comparable 28-day periods. Analytics are never treated as instructions, and changes are delivered as pull requests for review.</p>
            @if ($website->searchConsoleConnection->opportunities_checked_at)
                <p class="mt-1 text-xs text-slate-500">Last checked {{ $website->searchConsoleConnection->opportunities_checked_at->diffForHumans() }}.</p>
            @endif
        </div>
        <form method="POST" action="{{ route('admin.search-opportunities.refresh', $website) }}">
            @csrf
            <button class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Refresh opportunities</button>
        </form>
    </div>

    @if ($website->searchConsoleConnection->opportunities_error)
        <p class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">The latest opportunity analysis could not be completed. Try again, or reconnect Search Console if the problem continues.</p>
    @endif

    <div class="grid gap-4 p-4 lg:grid-cols-2">
        @forelse ($website->searchOpportunities as $opportunity)
            <article class="flex flex-col rounded-lg border border-slate-200 p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <span @class([
                        'rounded-full px-2.5 py-1 text-xs font-medium',
                        'bg-blue-100 text-blue-800' => $opportunity->type === 'ranking_gap',
                        'bg-violet-100 text-violet-800' => $opportunity->type === 'low_ctr',
                        'bg-red-100 text-red-800' => $opportunity->type === 'declining',
                        'bg-emerald-100 text-emerald-800' => $opportunity->type === 'emerging',
                        'bg-amber-100 text-amber-800' => $opportunity->type === 'cannibalisation',
                    ])>{{ str_replace('_', ' ', $opportunity->type) }}</span>
                    @if ($opportunity->status === 'queued')
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">Queued for improvement</span>
                    @endif
                </div>
                <h3 class="mt-3 font-semibold text-slate-950">{{ $opportunity->title }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $opportunity->summary }}</p>
                @if ($opportunity->page)
                    @if (Illuminate\Support\Str::startsWith($opportunity->page, ['https://', 'http://']))
                        <a href="{{ $opportunity->page }}" class="mt-2 truncate text-xs font-medium text-slate-600 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-900" rel="noopener noreferrer" target="_blank">{{ Illuminate\Support\Str::after($opportunity->page, '://') }}</a>
                    @else
                        <p class="mt-2 truncate text-xs text-slate-600">{{ $opportunity->page }}</p>
                    @endif
                @endif
                <p class="mt-3 flex-1 rounded-md bg-slate-50 p-3 text-sm leading-6 text-slate-700">{{ $opportunity->recommendation }}</p>
                @if ($opportunity->status === 'open')
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($website->repository || (Auth::user()?->isAdmin() && config('forms.pixel_ui_enabled') && $website->pixel_enabled))
                            <form method="POST" action="{{ route('admin.search-opportunities.queue', [$website, $opportunity]) }}">@csrf<button class="rounded-md bg-slate-900 px-3 py-2 text-xs font-medium text-white hover:bg-slate-800">Prepare improvement</button></form>
                        @else
                            <a href="{{ route('admin.website-repositories.create', $website) }}" class="rounded-md bg-slate-900 px-3 py-2 text-xs font-medium text-white">Connect GitHub or enable Pixel</a>
                        @endif
                        <form method="POST" action="{{ route('admin.search-opportunities.dismiss', [$website, $opportunity]) }}">@csrf @method('DELETE')<button class="rounded-md border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">Dismiss</button></form>
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600 lg:col-span-2">
                @if ($website->searchConsoleConnection->opportunities_checked_at)
                    No current opportunities met the reporting thresholds.
                @else
                    Run the first analysis to discover search opportunities.
                @endif
            </div>
        @endforelse
    </div>
</section>
@endif
