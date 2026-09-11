<x-email-layout>
<p style="font-family:Courier New,monospace;font-size:14px;color:#315a46;">Tomorrow's content run</p>
<h1 style="margin:4px 0 8px;font-size:24px;">Choose an idea for {{ $plan->website->name }}</h1>
<p style="color:#59685f;">The content todo queue is empty. Add any of these opportunities and Sitewell will prioritise it in the next run.</p>
@foreach ($searchOpportunities as $opportunity)
<div style="margin-top:14px;padding:16px;border:1px solid #dce3dd;border-radius:8px;">
<p style="margin:0 0 4px;font-size:14px;color:#59685f;">SEARCH CONSOLE</p><h2 style="margin:0;font-size:17px;">{{ $opportunity->title }}</h2><p style="color:#59685f;">{{ $opportunity->summary }}</p>
<a href="{{ $suggestionUrl('search', $opportunity->id) }}" style="display:inline-block;background:#dce9d9;color:#315a46;text-decoration:none;padding:12px 16px;border-radius:6px;">Add to content queue</a>
</div>
@endforeach
@foreach ($seoOpportunities as $opportunity)
<div style="margin-top:14px;padding:16px;border:1px solid #dce3dd;border-radius:8px;">
<p style="margin:0 0 4px;font-size:14px;color:#59685f;">SEO OPPORTUNITY</p><h2 style="margin:0;font-size:17px;">{{ $opportunity->title }}</h2><p style="color:#59685f;">{{ $opportunity->summary }}</p>
<a href="{{ $suggestionUrl('seo', $opportunity->id) }}" style="display:inline-block;background:#dce9d9;color:#315a46;text-decoration:none;padding:12px 16px;border-radius:6px;">Add to content queue</a>
</div>
@endforeach
<p style="margin-top:20px;color:#59685f;font-size:14px;">Links expire after 30 hours and require you to be signed in to Sitewell.</p>
</x-email-layout>
