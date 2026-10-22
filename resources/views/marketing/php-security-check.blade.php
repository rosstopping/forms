@extends('layouts.marketing')
@section('title', 'Is your website affected?')
@section('meta_description', 'Check whether your website could be affected by the latest PHP security issue and get help with the next step.')
@section('content')
<section class="mx-auto max-w-7xl px-5 pb-16 sm:px-8 lg:px-10">
    <div class="rounded-3xl bg-lichen px-6 py-10 sm:p-12">
        <p class="text-base font-medium text-garden sm:text-sm">Website security check · October 2026</p>
        <h1 class="mt-4 max-w-[24ch] text-balance text-4xl font-medium tracking-tight sm:text-6xl">Is your website affected?</h1>
        <p class="mt-5 max-w-[48ch] text-pretty text-lg text-ink/70">Enter your website below to check whether it could be affected by the latest security issue.</p>
        <form method="POST" action="{{ route('marketing.php-security-check.store') }}" class="mt-8 max-w-2xl">
            @csrf
            <label for="security-url" class="block text-base font-medium sm:text-sm">Your website address</label>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                <input id="security-url" name="url" type="text" inputmode="url" autocomplete="url" required maxlength="2048" placeholder="example.co.uk" value="{{ old('url', $result['url'] ?? '') }}" @if($errors->has('url')) aria-describedby="security-url-error" aria-invalid="true" @endif class="min-h-12 min-w-0 flex-1 rounded-xl bg-white px-4 py-3 text-base ring-1 ring-ink/20 focus:outline-2 focus:-outline-offset-1 focus:outline-garden">
                <button type="submit" class="min-h-12 rounded-full bg-garden px-5 py-3 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Check my website</button>
            </div>
            @error('url')<p id="security-url-error" class="mt-3 text-base text-red-700" role="alert">{{ $message }}</p>@enderror
            <div class="absolute -left-[9999rem]" aria-hidden="true"><label for="_sitewell_check">Leave this blank</label><input id="_sitewell_check" name="_sitewell_check" type="text" tabindex="-1" autocomplete="off"></div>
            @error('_sitewell_check')<p class="mt-3 text-base text-red-700" role="alert">Please refresh the page and try again.</p>@enderror
        </form>
    </div>
    @if($result)
        @php
            $potential = $result['status'] === 'potential';
            $unavailable = $result['status'] === 'unavailable';
            $heading = $potential ? 'Your website could be affected' : ($unavailable ? 'We couldn’t check your website' : 'We can’t tell from this check');
        @endphp
        <section aria-labelledby="result-heading" class="mt-8 rounded-3xl border border-ink/15 p-6 sm:p-10">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                <div aria-hidden="true" @class(['flex size-20 shrink-0 items-center justify-center rounded-full border-4 text-4xl font-medium', 'border-amber-400 text-amber-800' => $potential, 'border-ink/20 text-ink/60' => !$potential])>{{ $potential ? '!' : '?' }}</div>
                <div class="min-w-0">
                    <p class="break-all text-base text-ink/60 sm:text-sm">{{ $result['url'] }}</p>
                    <h2 id="result-heading" class="mt-2 text-balance text-2xl font-medium tracking-tight sm:text-3xl">{{ $heading }}</h2>
                    <p class="mt-4 max-w-[65ch] text-pretty text-base text-ink/75">{{ $potential ? 'Your website appears to use the software involved in this security issue. Your hosting provider may already have fixed it, so this result does not mean your website is vulnerable.' : ($unavailable ? 'We couldn’t get enough information from your website to give you a result. Try again later, or ask your hosting provider to check. This does not tell us whether your website is safe.' : 'We couldn’t establish whether your website uses the affected software. That doesn’t mean it’s safe. Your hosting provider can confirm whether the security update is needed.') }}</p>
                    <div class="mt-6 border-t border-ink/10 pt-6">
                        <h3 class="text-lg font-medium">What to do next</h3>
                        <p class="mt-2 max-w-[65ch] text-pretty text-base text-ink/75">Ask the company that hosts your website: “Has the October 2026 PHP security update been applied to my website?” If you’re unsure who to ask or need help getting it fixed, Ross can help.</p>
                        <a href="{{ route('marketing.contact') }}" class="mt-5 inline-flex min-h-12 items-center rounded-full px-5 py-3 text-base font-medium ring-1 ring-ink/25 hover:bg-lichen focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden">Ask Ross for help</a>
                    </div>
                </div>
            </div>
        </section>
    @endif
    <div class="mt-10 grid gap-8 lg:grid-cols-2">
        <div>
            <h2 class="text-balance text-2xl font-medium tracking-tight">Why this matters</h2>
            <p class="mt-4 max-w-[65ch] text-pretty text-base text-ink/70">A serious security issue has been found in PHP, software used to run many websites, including WordPress sites. This check helps you decide what to do next. Your hosting provider can confirm whether your website has the fix.</p>
        </div>
        <div>
            <h2 class="text-balance text-2xl font-medium tracking-tight">Who can fix it?</h2>
            <p class="mt-4 max-w-[65ch] text-pretty text-base text-ink/70">Your hosting provider needs to apply the PHP security update. Updating WordPress or its plugins won’t fix this issue. Some hosts received the fix before the announcement, so your website’s version number or last update date alone won’t tell you whether it’s protected.</p>
        </div>
    </div>
    <p class="mt-8 max-w-[85ch] text-pretty text-base text-ink/60 sm:text-sm">Guidance checked 22 October 2026. Read the <a href="https://www.php.net/archive/2026.php" class="underline underline-offset-4">official PHP announcement</a> and <a href="https://wordpress.org/news/2026/10/critical-php-security-release/" class="underline underline-offset-4">WordPress guidance</a>.</p>
</section>
@endsection
