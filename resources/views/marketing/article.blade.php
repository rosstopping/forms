@extends('layouts.marketing')

@section('title', $article['title'])
@section('meta_description', $article['excerpt'])

@section('content')
    <article class="py-16 sm:py-24">
        <header class="mx-auto max-w-5xl px-5 sm:px-8 lg:px-10">
            <a href="{{ route('marketing.journal') }}" class="text-base font-medium text-garden underline decoration-garden/25 underline-offset-4 hover:decoration-garden sm:text-sm">← Back to the journal</a>
            <p class="mt-10 font-mono text-sm uppercase tracking-wide text-garden">{{ $article['category'] }}</p>
            <h1 class="mt-5 max-w-[20ch] font-display text-5xl font-semibold tracking-tight text-pretty sm:text-6xl">{{ $article['title'] }}</h1>
            <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">{{ $article['excerpt'] }}</p>
            <p class="mt-6 font-mono text-sm text-ink/45">{{ $article['date'] }} · {{ $article['read_time'] }}</p>
        </header>
        <div class="prose mx-auto mt-16 max-w-[70ch] border-t border-ink/10 px-5 pt-12 sm:px-8 lg:px-10">
            @foreach ($article['sections'] as $section)
                <h2>{{ $section['heading'] }}</h2>
                <p>{{ $section['body'] }}</p>
            @endforeach
            <blockquote>Good website care is less about constant intervention and more about having the right signals, ownership, and next step.</blockquote>
        </div>
    </article>

    <x-marketing.cta />
@endsection
