@extends('layouts.marketing')

@section('title', 'Complete your Sitewell profile')
@section('meta_description', 'Complete your Sitewell profile and start your 14-day trial.')

@section('content')
<main class="isolate border-b border-ink/10 py-16 sm:py-24">
    <div class="mx-auto grid max-w-5xl gap-8 px-5 sm:px-8 lg:grid-cols-2 lg:px-10">
        <div class="grid content-start gap-5">
            <p class="font-mono text-sm font-medium uppercase tracking-wide text-moss">One last step</p>
            <h1 class="max-w-[24ch] font-display text-5xl font-semibold tracking-tight text-balance">Complete your profile</h1>
            <p class="max-w-[48ch] text-pretty text-lg text-ink/65">Your email is confirmed. Set up your profile to begin the 14-day Growth trial for {{ $audit->domain }}, including SEO performance features.</p>
        </div>
        <section class="rounded-2xl bg-[#fffefa] p-6 shadow-xl ring-1 ring-ink/10 sm:p-8" aria-labelledby="profile-details-title">
            <div class="grid gap-7">
                <h2 id="profile-details-title" class="max-w-[35ch] font-display text-3xl font-semibold tracking-tight text-balance">Your profile details</h2>
                <form method="POST" action="{{ request()->fullUrl() }}" class="grid max-w-xs gap-5">
                    @csrf
                    <div class="grid gap-2">
                        <label for="name" class="text-base font-medium sm:text-sm">Your name</label>
                        <input id="name" name="name" type="text" required autocomplete="name" value="{{ old('name') }}" class="w-full rounded-md border-0 bg-white px-3 py-3 text-base text-ink shadow-sm ring-1 ring-ink/15 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm">
                        @error('name')<p class="text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-2">
                        <label for="password" class="text-base font-medium sm:text-sm">Create a password</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="w-full rounded-md border-0 bg-white px-3 py-3 text-base text-ink shadow-sm ring-1 ring-ink/15 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm">
                        @error('password')<p class="text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-2">
                        <label for="password_confirmation" class="text-base font-medium sm:text-sm">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-md border-0 bg-white px-3 py-3 text-base text-ink shadow-sm ring-1 ring-ink/15 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm">
                    </div>
                    <button type="submit" class="rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-garden sm:text-sm">Start my 14-day Growth trial</button>
                </form>
            </div>
        </section>
    </div>
</main>
@endsection
