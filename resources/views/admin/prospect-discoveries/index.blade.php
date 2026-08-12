@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-teal-700">Lead generation</p><h1 class="text-3xl font-semibold tracking-tight">Find prospects</h1><p class="mt-1 text-sm text-slate-600">Find public business listings with websites, then choose exactly which ones to import.</p></div>
        <a href="{{ route('admin.prospects.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Back to Outreach</a>
    </div>
    @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="POST" action="{{ route('admin.prospect-discoveries.store') }}" class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">@csrf
            <label class="grid gap-1.5 text-sm font-medium text-slate-700">UK town, city or borough<input name="area" value="{{ old('area') }}" required placeholder="e.g. Bristol" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
            <label class="grid gap-1.5 text-sm font-medium text-slate-700">Business type<select name="business_type" required class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm">@foreach ($businessTypes as $value => $label)<option value="{{ $value }}" @selected(old('business_type') === $value)>{{ $label }}</option>@endforeach</select></label>
            <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Search public listings</button>
        </form>
        @error('area')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror @error('business_type')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        <p class="mt-4 text-xs leading-5 text-slate-500">Results are public OpenStreetMap business listings that include a website. Searches are cached for seven days to respect the shared service. Nothing is emailed or added to Outreach until you select and import it.</p>
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><div class="divide-y divide-slate-100">@forelse ($discoveries as $discovery)
        <a href="{{ route('admin.prospect-discoveries.show', $discovery) }}" class="grid gap-2 p-4 hover:bg-slate-50 md:grid-cols-[1fr_auto_auto] md:items-center"><div><p class="font-semibold text-slate-900">{{ $businessTypes[$discovery->business_type] ?? str($discovery->business_type)->headline() }} in {{ $discovery->area }}</p><p class="mt-1 text-sm text-slate-500">Started {{ $discovery->created_at->diffForHumans() }}</p></div><span class="text-sm text-slate-600">{{ $discovery->candidates_count }} candidates</span><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ str($discovery->status)->title() }}</span></a>
    @empty<div class="p-10 text-center"><p class="font-medium">No searches yet</p><p class="mt-1 text-sm text-slate-500">Choose an area and category to find businesses with public websites.</p></div>@endforelse</div></div>
    {{ $discoveries->links() }}
    <p class="text-xs text-slate-500">Data © <a href="https://www.openstreetmap.org/copyright" class="underline" target="_blank" rel="noreferrer">OpenStreetMap contributors</a>.</p>
</div>
@endsection
