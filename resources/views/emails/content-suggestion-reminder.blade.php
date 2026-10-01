<x-email-layout>
<p style="font-size:14px;font-weight:600;color:#d63d24;">Tomorrow's content run</p>
<h1 style="margin:4px 0 8px;font-size:24px;">Choose an idea for {{ $plan->website->name }}</h1>
<p style="color:#62666d;">The content todo queue is empty. Add any of these opportunities and Sitewell will prioritise it in the next run.</p>
@foreach ($searchOpportunities as $opportunity)
<div style="margin-top:14px;padding:16px;border:1px solid #ebe6e2;border-radius:8px;">
<p style="margin:0 0 4px;font-size:14px;color:#62666d;">SEARCH CONSOLE</p><h2 style="margin:0;font-size:17px;">{{ $opportunity->title }}</h2><p style="color:#62666d;">{{ $opportunity->summary }}</p>
<a href="{{ $suggestionUrl('search', $opportunity->id) }}" style="display:inline-block;background:#d63d24;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:999px;font-weight:600;">Add to content queue</a>
</div>
@endforeach
@foreach ($seoOpportunities as $opportunity)
<div style="margin-top:14px;padding:16px;border:1px solid #ebe6e2;border-radius:8px;">
<p style="margin:0 0 4px;font-size:14px;color:#62666d;">SEO OPPORTUNITY</p><h2 style="margin:0;font-size:17px;">{{ $opportunity->title }}</h2><p style="color:#62666d;">{{ $opportunity->summary }}</p>
<a href="{{ $suggestionUrl('seo', $opportunity->id) }}" style="display:inline-block;background:#d63d24;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:999px;font-weight:600;">Add to content queue</a>
</div>
@endforeach
<p style="margin-top:20px;color:#62666d;font-size:14px;">Links expire after 30 hours and require you to be signed in to Sitewell.</p>
</x-email-layout>
