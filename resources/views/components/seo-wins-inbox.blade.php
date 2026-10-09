@props(['wins', 'status', 'siteFilter' => null, 'category' => 'win'])
<div class="space-y-5">
    <div class="ui-panel ui-section"><h2 class="text-xl font-semibold">{{ match($category) { 'alert' => 'SEO alerts', 'opportunity' => 'SEO opportunities', default => 'Sitewell Wins' } }}</h2><p class="mt-2 text-sm text-slate-600">Evidence from saved ranking checks, finalized Search Console periods and content measurements. Alerts and opportunities require review; they do not create content tasks. Review and approve the wording, then copy it to your client. Only shared, approved Wins appear on the client timeline. Copying does not send a message or mark it as shared.</p>
        <nav class="ui-tabs mt-4" aria-label="SEO signal category">@foreach(['win' => 'Wins', 'alert' => 'Alerts', 'opportunity' => 'Opportunities'] as $key => $label)<a class="ui-tab" href="{{ route('admin.overview', array_filter(['hub' => 'wins', 'site_id' => $siteFilter, 'signal_category' => $key])) }}" @if($category === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
        <nav class="ui-tabs mt-4" aria-label="Win status">@foreach(['unshared' => 'To share', 'shared' => 'Shared', 'dismissed' => 'Dismissed'] as $key => $label)<a class="ui-tab" href="{{ route('admin.overview', array_filter(['hub' => 'wins', 'site_id' => $siteFilter, 'win_status' => $key, 'signal_category' => $category])) }}" @if($status === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
    </div>
    @forelse($wins as $win)
        <article class="ui-panel ui-section space-y-4">
            <header><p class="text-sm font-medium text-teal-700">{{ $win->website->name }} · {{ ucfirst($win->importance) }} importance</p><h3 class="mt-1 font-semibold">{{ $win->title }}</h3><p class="mt-1 text-sm text-slate-500">First observed {{ $win->observed_at->format('j M Y') }} · Confirmed {{ $win->confirmed_at->format('j M Y') }} · {{ ucfirst($win->confidence) }} confidence</p></header>
            <div class="ui-well p-4 text-sm text-slate-600">@if(data_get($win->evidence, 'source'))
                <p>Google Search Console · {{ data_get($win->evidence, 'start') }}–{{ data_get($win->evidence, 'end') }}</p>
                <p class="mt-2">{{ number_format(data_get($win->evidence, 'current.clicks', 0)) }} clicks · {{ number_format(data_get($win->evidence, 'current.impressions', 0)) }} impressions.</p>
                @if(data_get($win->evidence, 'previous_start'))<p>Comparison: {{ data_get($win->evidence, 'previous_start') }}–{{ data_get($win->evidence, 'previous_end') }} · {{ number_format(data_get($win->evidence, 'previous.clicks', 0)) }} clicks.</p>@endif
                @if(data_get($win->evidence, 'year_comparison'))<p>Seasonal context: {{ number_format(data_get($win->evidence, 'year_comparison.clicks')) }} clicks in the equivalent period last year ({{ data_get($win->evidence, 'year_comparison.start') }}–{{ data_get($win->evidence, 'year_comparison.end') }}).</p>@endif
                @if(data_get($win->evidence, 'confirmation.end'))<p>Confirmed in a second window ending {{ data_get($win->evidence, 'confirmation.end') }}.</p>@endif
                @foreach(data_get($win->evidence, 'urls', []) as $url)<p class="mt-2 break-all">{{ $url }}</p>@endforeach
                @if(data_get($win->evidence, 'live_at'))<p>Publication/change recorded: {{ data_get($win->evidence, 'live_at') }} · Checkpoint: day {{ data_get($win->evidence, 'checkpoint') }}.</p>@endif
                <p class="mt-2">Search clicks are not visits or enquiries. Seasonal demand and other changes may contribute; causation has not been established.</p>
                @else<p>Google desktop ranking checks · Location {{ data_get($win->evidence, 'market.location_code') }} · Language {{ data_get($win->evidence, 'market.language_code') }}</p>
                <ul class="mt-2 space-y-1">@foreach(data_get($win->evidence, 'observations', []) as $observation)<li>{{ \Illuminate\Support\Carbon::parse($observation['observed_at'])->format('j M Y') }}: {{ $observation['position'] === null ? 'Outside the checked top 100' : 'Position '.$observation['position'] }}@if($observation['url']) · <span class="break-all">{{ $observation['url'] }}</span>@endif</li>@endforeach</ul>
                @if(data_get($win->evidence, 'intended_url'))<p class="mt-2 break-all">Intended destination: {{ $win->evidence['intended_url'] }}</p>@endif
                <p class="mt-2">This is a ranking milestone. Traffic impact and the cause of the movement have not been established.</p>@endif
            </div>
            @if($win->shared_at)
                <p class="text-sm text-teal-700">Marked shared {{ $win->shared_at->format('j M Y, H:i') }}</p><p class="whitespace-pre-wrap text-sm text-slate-600">{{ $win->shared_text }}</p>
            @elseif($win->dismissed_at)
                <p class="text-sm text-slate-500">Dismissed {{ $win->dismissed_at->format('j M Y') }}</p>
            @else
                <form method="POST" action="{{ route('admin.seo-wins.update', $win) }}" class="space-y-3">@csrf @method('PATCH')
                    <label for="win-draft-{{ $win->id }}" class="ui-label">Client update</label><textarea id="win-draft-{{ $win->id }}" name="client_draft" maxlength="2000" rows="4" class="ui-input w-full">{{ $win->client_draft }}</textarea>
                    <div class="flex flex-wrap gap-2"><button name="action" value="save" class="ui-button ui-button-secondary">Save draft</button><button name="action" value="approve" class="ui-button ui-button-primary">Approve update</button><button name="action" value="dismiss" class="ui-button ui-button-secondary">Dismiss win</button></div>
                </form>
                @if($win->approved_at)
                    <div class="space-y-2"><label for="win-approved-{{ $win->id }}" class="ui-label">Approved text · {{ $win->approved_at->format('j M Y') }}</label><textarea id="win-approved-{{ $win->id }}" readonly rows="4" class="ui-input w-full">{{ $win->client_draft }}</textarea>
                    <div class="flex flex-wrap gap-2"><button type="button" class="ui-button ui-button-secondary js-copy-text" data-copy-target="win-approved-{{ $win->id }}" data-copy-label="Copy approved text" data-copied-label="Copied">Copy approved text</button>
                    <form method="POST" action="{{ route('admin.seo-wins.update', $win) }}">@csrf @method('PATCH')<button name="action" value="share" class="ui-button ui-button-secondary">Mark as shared</button></form></div></div>
                @endif
            @endif
        </article>
    @empty<div class="ui-panel ui-section text-sm text-slate-600">No {{ $status }} {{ $category === 'win' ? 'wins' : ($category === 'alert' ? 'alerts' : 'opportunities') }} yet. Detection uses existing checks and waits for a milestone to persist across two distinct observation dates at least three days apart.</div>@endforelse
    {{ $wins->links() }}
</div>
