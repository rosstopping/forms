@extends('layouts.marketing')

@section('title', 'Email preferences | Sitewell')
@section('content')
<section class="mx-auto grid max-w-xl gap-6 px-5 py-16">
    <h1 class="text-3xl font-medium tracking-tight">Email preferences</h1>
    @if (session('status'))
        <p role="status" class="text-base text-ink/65">{{ session('status') }}</p>
    @elseif ($audit->marketing_consent_at)
        <p class="text-base text-ink/65">You can unsubscribe from ongoing website advice. You can still receive the personal review you requested.</p>
        <form method="POST" class="grid gap-4">
            @csrf
            <button type="submit" class="min-h-12 rounded-full bg-garden px-5 py-3 font-medium text-white hover:bg-moss">Unsubscribe from website advice</button>
        </form>
    @else
        <p class="text-base text-ink/65">You are not subscribed to ongoing website advice.</p>
    @endif
</section>
@endsection
