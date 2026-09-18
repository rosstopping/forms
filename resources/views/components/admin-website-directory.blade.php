@props(['websites'])
<div class="space-y-5">
    <header><h2 class="text-xl font-semibold text-balance">Your websites</h2><p class="mt-2 text-base text-slate-600 sm:text-sm">Check the latest saved health report and jump straight into a website’s work.</p></header>
    <div class="@container">
        <div class="divide-y divide-slate-950/10">
            @forelse ($websites as $website)
                @php($report = $website->latestHealthReport)
                <article class="grid gap-4 py-5 first:pt-0 @3xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] @3xl:items-center">
                    <div class="min-w-0"><h3 class="break-words font-semibold">{{ $website->name }}</h3><p class="mt-1 break-all text-base text-slate-500 sm:text-sm">{{ $website->primaryDomain()?->domain ?: 'No primary domain' }}</p><p class="mt-2 text-base text-slate-600 sm:text-sm">{{ $website->is_active ? 'Active' : 'Paused' }} · {{ $website->pending_content_requests_count }} requests awaiting preparation</p></div>
                    <div class="text-base sm:text-sm">
                        <p class="font-medium {{ $report?->failed_checks ? 'text-rose-700' : 'text-slate-700' }}">{{ ! $report ? 'Not audited yet' : ($report->status !== 'completed' ? 'Latest audit: '.str_replace('_', ' ', $report->status) : ($report->failed_checks.' failed · '.$report->warning_checks.' warnings')) }}</p>
                        @if ($report?->completed_at)<p class="mt-1 text-slate-500">Checked {{ $report->completed_at->format('j M Y') }}</p>@endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.websites.section', [$website, 'health']) }}" class="ui-button ui-button-secondary">Website health</a>
                        <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'actions']) }}" class="ui-button ui-button-secondary">Action list</a>
                        <a href="{{ route('admin.websites.section', [$website, 'content']) }}" class="ui-button ui-button-secondary">Content</a>
                    </div>
                </article>
            @empty
                <div class="ui-well p-6"><h3 class="font-semibold">No websites yet</h3><p class="mt-2 text-base text-slate-600 sm:text-sm">Your connected websites and their work will appear here.</p></div>
            @endforelse
        </div>
    </div>
    {{ $websites->links() }}
</div>
