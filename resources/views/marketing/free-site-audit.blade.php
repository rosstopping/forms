@extends('layouts.marketing')

@section('title', 'Free website audit')
@section('meta_description', 'Get a free Sitewell audit covering website health, search essentials, accessibility, security, and discoverability.')

@section('content')
<section class="border-b border-ink/10">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 py-16 sm:px-8 sm:py-24 lg:grid-cols-[1.05fr_.95fr] lg:items-start lg:px-10">
        <div class="lg:pt-8">
            <p class="font-mono text-sm font-medium uppercase tracking-widest text-moss">Free website audit</p>
            <h1 class="mt-5 max-w-[12ch] font-display text-5xl font-semibold leading-[.98] tracking-tight text-balance sm:text-6xl">Find out what your website needs next.</h1>
            <p class="mt-7 max-w-2xl text-lg leading-8 text-ink/70">We’ll check the public signals that affect trust, visibility, accessibility, and lead generation, then email you a clear report with practical priorities.</p>

            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                @foreach ([['Website health', 'Availability, response time, HTTPS, and discoverability.'], ['Search essentials', 'Titles, descriptions, headings, crawl signals, and structured data.'], ['Accessibility', 'Mobile setup, language signals, and useful image text.'], ['Security basics', 'Public browser protections and important response headers.']] as [$title, $description])
                    <article class="rounded-xl border border-ink/10 bg-white/40 p-5"><h2 class="font-medium text-ink">{{ $title }}</h2><p class="mt-2 text-sm leading-6 text-ink/65">{{ $description }}</p></article>
                @endforeach
            </div>
            <p class="mt-8 text-sm leading-6 text-ink/55">No obligation and no access to your website is required. The audit uses publicly available information and is designed as a useful starting point.</p>
        </div>

        <div class="rounded-2xl bg-[#fffefa] p-6 shadow-xl ring-1 ring-ink/10 sm:p-8">
            <h2 class="font-display text-3xl font-semibold tracking-tight">Get your free audit</h2>
            <p class="mt-2 text-sm leading-6 text-ink/65">Your results will be prepared in the background and sent to your inbox.</p>

            @if (session('status'))
                <div class="mt-6 rounded-lg border border-moss/20 bg-lichen px-4 py-3 text-sm text-ink">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('marketing.free-site-audit.store') }}" class="mt-7 grid gap-5">
                @csrf
                <div><label for="name" class="text-sm font-medium">Your name</label><input id="name" name="name" type="text" required autocomplete="name" value="{{ old('name') }}" class="mt-1.5 w-full rounded-md border border-ink/20 bg-white px-3 py-2.5">@error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div><label for="email" class="text-sm font-medium">Work email</label><input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" class="mt-1.5 w-full rounded-md border border-ink/20 bg-white px-3 py-2.5">@error('email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div><label for="business_name" class="text-sm font-medium">Business name</label><input id="business_name" name="business_name" type="text" required autocomplete="organization" value="{{ old('business_name') }}" class="mt-1.5 w-full rounded-md border border-ink/20 bg-white px-3 py-2.5">@error('business_name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div><label for="website_url" class="text-sm font-medium">Website address</label><input id="website_url" name="website_url" type="text" required inputmode="url" autocomplete="url" placeholder="example.com" value="{{ old('website_url') }}" class="mt-1.5 w-full rounded-md border border-ink/20 bg-white px-3 py-2.5">@error('website_url')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div class="absolute left-[-9999px] size-px overflow-hidden" aria-hidden="true"><label for="_sitewell_check">Leave this field empty</label><input id="_sitewell_check" name="_sitewell_check" type="text" tabindex="-1" autocomplete="off"></div>
                <label class="flex items-start gap-3 text-sm leading-6 text-ink/65"><input name="consent" type="checkbox" value="1" required class="mt-1 rounded border-ink/30"><span>I agree to Sitewell using these details to prepare and email my audit and to follow up about the findings.</span></label>
                @error('consent')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                <button type="submit" class="rounded-md bg-ink px-5 py-3 font-medium text-paper hover:bg-ink/90">Run my free audit</button>
            </form>
        </div>
    </div>
</section>
@endsection
