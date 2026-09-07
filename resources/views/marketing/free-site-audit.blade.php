@extends('layouts.marketing')

@section('title', 'Get started')
@section('meta_description', 'Enter your website address to get started with Sitewell and see what your website needs next.')

@section('content')
<section class="border-b border-ink/10 py-16 sm:py-24">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[21fr_19fr] lg:items-center lg:px-10">
        <div class="grid gap-7">
            <div class="grid gap-5">
                <p class="font-mono text-sm font-medium uppercase tracking-wide text-moss">Get started</p>
                <h1 class="max-w-[12ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">See what your website needs next.</h1>
                <p class="max-w-[48ch] text-pretty text-lg text-ink/70">Enter your website address. Sitewell will immediately review the public signals affecting its health, visibility, accessibility, and trust.</p>
            </div>
            <dl class="grid gap-6 border-t border-ink/10 pt-7 sm:grid-cols-2">
                @foreach ([['Website health', 'Availability, response time, HTTPS, and crawl signals.'], ['Search essentials', 'Titles, descriptions, headings, and structured data.'], ['Accessibility', 'Mobile setup, language signals, and useful image text.'], ['Security basics', 'Important public browser protections and response headers.']] as [$title, $description])
                    <div class="grid gap-2">
                        <dt class="font-medium text-ink">{{ $title }}</dt>
                        <dd class="text-pretty text-base text-ink/60 sm:text-sm">{{ $description }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
        <div class="rounded-2xl bg-[#fffefa] p-6 shadow-xl ring-1 ring-ink/10 sm:p-8">
            <div class="grid gap-7">
                <div class="grid gap-2">
                    <h2 class="max-w-[35ch] font-display text-3xl font-semibold tracking-tight text-balance">Enter your website below to get started</h2>
                    <p class="max-w-[56ch] text-pretty text-base text-ink/65 sm:text-sm">No account, email address, or website access is needed.</p>
                </div>
                <form method="POST" action="{{ route('marketing.free-site-audit.store') }}" class="grid max-w-xs gap-5">
                    @csrf
                    <div class="grid gap-2">
                        <label for="website_url" class="text-base font-medium sm:text-sm">Website address</label>
                        <input id="website_url" name="website_url" type="text" required autofocus inputmode="url" autocomplete="url" placeholder="example.com" value="{{ old('website_url') }}" aria-invalid="{{ $errors->has('website_url') ? 'true' : 'false' }}" class="w-full rounded-md border-0 bg-white px-3 py-3 text-base text-ink shadow-sm ring-1 ring-ink/15 placeholder:text-ink/35 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm">
                        @error('website_url')<p class="text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
                    </div>
                    <div class="absolute left-[-9999px] size-px overflow-hidden" aria-hidden="true">
                        <label for="_sitewell_check">Leave this field empty</label>
                        <input id="_sitewell_check" name="_sitewell_check" type="text" tabindex="-1" autocomplete="off">
                    </div>
                    @if ($turnstileEnabled)
                        <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-theme="light"></div>
                        @error('cf-turnstile-response')<p class="text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
                    @endif
                    <button type="submit" class="rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-garden sm:text-sm">Get started</button>
                </form>
                <p class="max-w-[56ch] text-pretty text-base text-ink/50 sm:text-sm">We use only publicly available information. You decide whether to continue after seeing the results.</p>
            </div>
        </div>
    </div>
</section>
@if ($turnstileEnabled)
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
@endsection
