@props(['sections'])

@foreach ($sections as $section)
    <h2 id="{{ Illuminate\Support\Str::slug($section['heading']) }}">{{ $section['heading'] }}</h2>
    @foreach ($section['paragraphs'] ?? [$section['body'] ?? ''] as $paragraph)
        @if ($paragraph !== '')<p>{{ $paragraph }}</p>@endif
    @endforeach
    @if (! empty($section['points']))
        <ul role="list">
            @foreach ($section['points'] as $point)<li>{{ $point }}</li>@endforeach
        </ul>
    @endif
    @if (! empty($section['steps']))
        <ol role="list">
            @foreach ($section['steps'] as $step)<li>{{ $step }}</li>@endforeach
        </ol>
    @endif
    @if (! empty($section['table']))
        <div class="-mx-5 -my-2 overflow-x-auto sm:-mx-8 lg:-mx-10" role="region" aria-label="{{ $section['heading'] }} comparison" tabindex="0">
            <div class="inline-block min-w-full px-5 py-2 align-middle sm:px-8 lg:px-10">
                <table class="w-full text-left text-base sm:text-sm">
                    <caption class="sr-only">{{ $section['heading'] }}</caption>
                    <thead><tr class="border-b border-ink/20">
                        @foreach ($section['table']['headers'] as $heading)<th scope="col" class="px-3 py-3 font-medium whitespace-nowrap">{{ $heading }}</th>@endforeach
                    </tr></thead>
                    <tbody>
                        @foreach ($section['table']['rows'] as $row)
                            <tr class="border-b border-ink/10">
                                @foreach ($row as $cell)<td class="min-w-40 px-3 py-4 align-top text-ink/75">{{ $cell }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    @if (! empty($section['paragraph_note']))
        <aside class="border-l-2 border-garden bg-lichen/40 p-5"><p>{{ $section['paragraph_note'] }}</p></aside>
    @endif
    @if (! empty($section['link']))
        <p>{{ $section['link']['before'] }}<a href="{{ route($section['link']['route'], $section['link']['parameters'] ?? []) }}">{{ $section['link']['label'] }}</a>{{ $section['link']['after'] }}</p>
    @endif
    @if (! empty($section['links']))
        <ul role="list">
            @foreach ($section['links'] as $link)
                <li><a href="{{ route($link['route'], $link['parameters'] ?? []) }}">{{ $link['label'] }}</a>@if (! empty($link['description'])): {{ $link['description'] }}@endif</li>
            @endforeach
        </ul>
    @endif
    @if (! empty($section['source']))
        <p class="text-base text-ink/65 sm:text-sm">Source: <a href="{{ $section['source']['url'] }}">{{ $section['source']['label'] }}</a>.</p>
    @endif
@endforeach
