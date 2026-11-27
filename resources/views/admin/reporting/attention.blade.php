<section id="attention" class="ui-panel ui-section" aria-labelledby="attention-title">
    <h2 id="attention-title" class="text-lg font-semibold">Needs attention <span class="ml-1 text-sm font-normal tabular-nums text-slate-600">({{ $attention->count() }})</span></h2>
    @if($attention->isEmpty())
        <p class="mt-2 text-slate-600">No outstanding alerts.</p>
    @else
        @foreach([$attention->take(4), $attention->skip(4)] as $group)
            @if($group->isNotEmpty())
                @if($loop->last)<details class="mt-3"><summary class="cursor-pointer font-medium text-teal-800">Show {{ $group->count() }} more</summary>@endif
                <ul class="mt-2 divide-y divide-slate-100">
                    @foreach($group as $item)
                        <li class="py-2"><a href="{{ $item['url'] }}" class="break-words text-teal-800 underline decoration-teal-800/30 underline-offset-4">@if($portfolio ?? false)<strong>{{ $item['site'] }}</strong> · @endif{{ $item['title'] }}</a></li>
                    @endforeach
                </ul>
                @if($loop->last)</details>@endif
            @endif
        @endforeach
    @endif
</section>
