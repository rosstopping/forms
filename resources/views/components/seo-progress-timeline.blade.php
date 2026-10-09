@props(['items', 'compact' => false])
<section class="ui-panel ui-section" aria-labelledby="seo-progress-heading">
    <h2 id="seo-progress-heading" class="text-lg font-semibold">SEO progress timeline</h2>
    <p class="mt-2 text-sm text-slate-600">Completed work, recorded publication and approved search achievements. Events appearing together do not establish that the work caused a performance change.</p>
    @if($compact && $items->isNotEmpty())<p class="mt-3 truncate text-sm text-slate-700">Latest: {{ $items->first()['at']->format('j M') }} · {{ $items->first()['title'] }}</p>@endif
    @if($compact)<details class="mt-4"><summary class="cursor-pointer text-sm font-medium text-teal-700">{{ $items->count() }} recent updates · View progress timeline</summary>@endif
    <ol class="mt-5 space-y-5">
        @forelse($items as $item)
            <li class="ui-well p-4">
                <p class="text-xs font-medium text-teal-700">{{ $item['type'] }} · <time datetime="{{ $item['at']->toIso8601String() }}">{{ $item['at']->format('j M Y') }}</time></p>
                <h3 class="mt-1 font-semibold">{{ $item['title'] }}</h3>
                <p class="mt-2 whitespace-pre-wrap text-sm text-slate-600">{{ $item['text'] }}</p>
                @foreach($item['urls'] as $url)<p class="mt-2 break-all text-sm text-slate-500">{{ $url }}</p>@endforeach
                @if($item['keywords'] !== [])<p class="mt-2 text-sm text-slate-500">Intended keywords: {{ implode(', ', $item['keywords']) }}</p>@endif
            </li>
        @empty
            <li class="text-sm text-slate-500">Your progress timeline will appear as work is completed and achievements are reviewed.</li>
        @endforelse
    </ol>
    @if($compact)</details>@endif
</section>
