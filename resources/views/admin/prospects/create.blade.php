@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div><a href="{{ route('admin.prospects.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">← Back to outreach</a><h1 class="mt-3 text-3xl font-semibold tracking-tight">Add a prospect</h1><p class="mt-1 text-slate-600 text-base sm:text-sm">Add a website to run research, or leave it blank to create a website opportunity.</p></div>
    <form method="POST" action="{{ route('admin.prospects.store') }}" class="ui-panel ui-section space-y-5">@csrf
        <div><label for="business_name" class="ui-label">Business name</label><input id="business_name" name="business_name" value="{{ old('business_name') }}" required class="ui-input mt-1 w-full">@error('business_name')<p class="mt-1 text-red-600 text-base sm:text-sm">{{ $message }}</p>@enderror</div>
        <div class="grid gap-4 sm:grid-cols-2"><div><label for="contact_name" class="ui-label">Contact name</label><input id="contact_name" name="contact_name" value="{{ old('contact_name') }}" class="ui-input mt-1 w-full"></div><div><label for="email" class="ui-label">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" class="ui-input mt-1 w-full">@error('email')<p class="mt-1 text-red-600 text-base sm:text-sm">{{ $message }}</p>@enderror</div></div>
        <div><label for="website_url" class="ui-label">Website URL <span class="font-normal text-slate-500">(optional)</span></label><input id="website_url" type="url" name="website_url" value="{{ old('website_url') }}" placeholder="https://example.com" class="ui-input mt-1 w-full"><p class="mt-1 text-slate-500 text-base sm:text-sm">Leave blank when no website is listed; Sitewell will create a website-sales draft and skip the audit.</p>@error('website_url')<p class="mt-1 text-red-600 text-base sm:text-sm">{{ $message }}</p>@enderror</div>
        <div><label for="showcase_video_url" class="ui-label">Showcase video URL <span class="font-normal text-slate-500">(optional)</span></label><input id="showcase_video_url" type="url" name="showcase_video_url" value="{{ old('showcase_video_url') }}" placeholder="https://www.loom.com/share/..." class="ui-input mt-1 w-full"><p class="mt-1 text-slate-500 text-base sm:text-sm">The unique video for this prospect. It must be added before a test or live email can be sent.</p>@error('showcase_video_url')<p class="mt-1 text-red-600 text-base sm:text-sm">{{ $message }}</p>@enderror</div>
        <div><label for="notes" class="ui-label">Research notes <span class="font-normal text-slate-500">(optional)</span></label><textarea id="notes" name="notes" rows="4" class="ui-input mt-1 w-full">{{ old('notes') }}</textarea></div>
        <button type="submit" class="ui-button ui-button-primary">Add prospect</button>
    </form>
</div>
@endsection
