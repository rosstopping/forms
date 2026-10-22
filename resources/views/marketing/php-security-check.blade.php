@extends('layouts.marketing')
@section('title', 'PHP security checker')
@section('meta_description', 'Check your website for public PHP and WordPress clues and find out what to ask your hosting provider about the October 2026 PHP security fixes.')
@section('content')
<section class="mx-auto max-w-7xl px-5 pb-16 sm:px-8 lg:px-10">
    <div class="rounded-3xl bg-lichen px-6 py-10 sm:p-12">
        <p class="text-base font-medium text-garden sm:text-sm">PHP security · October 2026</p>
        <h1 class="mt-4 max-w-[24ch] text-balance text-4xl font-medium tracking-tight sm:text-6xl">Does your website need a PHP check?</h1>
        <p class="mt-5 max-w-[48ch] text-pretty text-lg text-ink/70">Enter your website to look for public PHP or WordPress clues. Get your result straight away, with no email required.</p>
        <form method="POST" action="{{ route('marketing.php-security-check.store') }}" class="mt-8 max-w-2xl">
            @csrf
            <label for="security-url" class="block text-base font-medium sm:text-sm">Your website address</label>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                <input id="security-url" name="url" type="text" inputmode="url" autocomplete="url" required maxlength="2048" placeholder="example.co.uk" value="{{ old('url', $result['url'] ?? '') }}" aria-describedby="security-url-help{{ $errors->has('url') ? ' security-url-error' : '' }}" @if($errors->has('url')) aria-invalid="true" @endif class="min-h-12 min-w-0 flex-1 rounded-xl bg-white px-4 py-3 text-base ring-1 ring-ink/20 focus:outline-2 focus:-outline-offset-1 focus:outline-garden">
                <button type="submit" class="min-h-12 rounded-full bg-garden px-5 py-3 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Check my website</button>
            </div>
            <p id="security-url-help" class="mt-3 text-base text-ink/65 sm:text-sm">We read the public page only. No login, changes or attack attempts.</p>
            @error('url')<p id="security-url-error" class="mt-3 text-base text-red-700" role="alert">{{ $message }}</p>@enderror
            <div class="absolute -left-[9999rem]" aria-hidden="true"><label for="_sitewell_check">Leave this blank</label><input id="_sitewell_check" name="_sitewell_check" type="text" tabindex="-1" autocomplete="off"></div>
            @error('_sitewell_check')<p class="mt-3 text-base text-red-700" role="alert">Please leave the hidden field blank and try again.</p>@enderror
        </form>
    </div>
    @if($result)
        @php
            $potential = $result['status'] === 'potential';
            $unavailable = $result['status'] === 'unavailable';
            $heading = $potential ? 'Potentially affected — ask your host' : ($unavailable ? 'We couldn’t check this website' : 'Unable to confirm PHP from the public page');
        @endphp
        <section aria-labelledby="result-heading" class="mt-8 rounded-3xl border border-ink/15 p-6 sm:p-10">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                <div aria-hidden="true" @class(['flex size-20 shrink-0 items-center justify-center rounded-full border-4 text-4xl font-medium', 'border-amber-400 text-amber-800' => $potential, 'border-ink/20 text-ink/60' => !$potential])>{{ $potential ? '!' : '?' }}</div>
                <div class="min-w-0">
                    <p class="break-all text-base text-ink/60 sm:text-sm">{{ $result['url'] }}</p>
                    <h2 id="result-heading" class="mt-2 text-balance text-2xl font-medium tracking-tight sm:text-3xl">{{ $heading }}</h2>
                    <p class="mt-4 max-w-[65ch] text-pretty text-base text-ink/75">{{ $potential ? 'We found clues that your website may use PHP. This does not confirm a vulnerability or show whether your host has already patched it.' : ($unavailable ? 'The page may be offline, block automated checks, redirect somewhere we cannot check, or exceed our check limits. We could not assess its PHP setup.' : 'We found no clear PHP or WordPress clues in this page. PHP can be hidden behind a cache or hosting setup, so this does not mean your website is safe.') }}</p>
                    @foreach($result['evidence'] as $evidence)<p class="mt-3 max-w-[65ch] text-pretty text-base text-ink/70">{{ $evidence }}</p>@endforeach
                    <div class="mt-6 border-t border-ink/10 pt-6">
                        <h3 class="text-lg font-medium">Your next step</h3>
                        <p class="mt-2 max-w-[65ch] text-pretty text-base text-ink/75">Ask your hosting provider to confirm that the PHP package serving your website includes the fixes for CVE-2026-109618 and CVE-2026-109619. Request the package version and their patch advisory, including any backported fixes.</p>
                        <a href="{{ route('marketing.contact') }}" class="mt-5 inline-flex min-h-12 items-center rounded-full px-5 py-3 text-base font-medium ring-1 ring-ink/25 hover:bg-lichen focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden">Ask Ross to help verify or fix my hosting</a>
                    </div>
                </div>
            </div>
        </section>
    @endif
    <div class="mt-10 grid gap-8 lg:grid-cols-2">
        <div>
            <h2 class="text-balance text-2xl font-medium tracking-tight">What this check can tell you</h2>
            <p class="mt-4 max-w-[65ch] text-pretty text-base text-ink/70">It looks at the page and response headers for clues. It cannot inspect hosting packages, prove a vulnerability, or confirm that a fix has been applied. It is not a full security audit.</p>
        </div>
        <div>
            <h2 class="text-balance text-2xl font-medium tracking-tight">PHP needs its own update</h2>
            <p class="mt-4 max-w-[65ch] text-pretty text-base text-ink/70">Updating WordPress, themes or plugins does not patch PHP. Hosting vendors received fixes in advance and may backport them, so an older version number or update date alone cannot prove a website is affected.</p>
        </div>
    </div>
    <p class="mt-8 max-w-[85ch] text-pretty text-base text-ink/60 sm:text-sm">Guidance checked 22 October 2026. Read the <a href="https://www.php.net/archive/2026.php" class="underline underline-offset-4">PHP security advisory</a> and <a href="https://wordpress.org/news/2026/10/critical-php-security-release/" class="underline underline-offset-4">WordPress guidance</a>. Your host should confirm the patch status for your actual website.</p>
</section>
@endsection
