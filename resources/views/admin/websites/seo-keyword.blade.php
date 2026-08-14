@extends('layouts.app')

@section('content')
    <div class="space-y-8">
        <header>
            <a href="{{ route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'keywords']) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">← Back to SEO keywords</a>
            <p class="mt-6 text-xs font-medium uppercase tracking-wide text-slate-500">SEO Intelligence keyword</p>
            <h1 class="mt-1 text-balance text-2xl font-semibold tracking-tight text-slate-950">{{ $seoKeyword->keyword }}</h1>
            <p class="mt-2 text-pretty text-base text-slate-600 sm:text-sm">Third-party ranking and traffic estimates captured in detailed Sitewell snapshots. Aggregate historical backfills do not contain individual keyword rows.</p>
        </header>

        <section aria-labelledby="keyword-history-heading">
            <h2 id="keyword-history-heading" class="text-lg font-semibold text-slate-950">Keyword performance over time</h2>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <x-progress-chart title="Google position" description="Estimated organic ranking; lower is better." :points="$observations" value-key="position" format="decimal" :lower-is-better="true" />
                <x-progress-chart title="Estimated traffic" description="Estimated monthly organic visits attributed to this keyword." :points="$observations" value-key="estimated_traffic" format="traffic" />
                <x-progress-chart title="Search volume" description="Estimated monthly searches for this keyword." :points="$observations" value-key="search_volume" />
            </div>
        </section>
    </div>
@endsection
