<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Content ideas</title></head>
<body style="font-family:Arial,sans-serif;background:#f6f8fb;padding:24px;color:#111827;">
<div style="max-width:640px;margin:0 auto;background:white;border-radius:12px;padding:24px;">
    <p style="font-size:12px;text-transform:uppercase;color:#64748b;">Tomorrow's content run</p>
    <h1 style="margin:4px 0 8px;font-size:24px;">Choose an idea for {{ $plan->website->name }}</h1>
    <p style="color:#475569;">The content todo queue is empty. Add any of these opportunities and Sitewell will prioritise it in the next run.</p>
    @foreach ($searchOpportunities as $opportunity)
        <div style="margin-top:14px;padding:16px;border:1px solid #e2e8f0;border-radius:8px;">
            <p style="margin:0 0 4px;font-size:12px;color:#64748b;">SEARCH CONSOLE</p><h2 style="margin:0;font-size:17px;">{{ $opportunity->title }}</h2><p style="color:#475569;">{{ $opportunity->summary }}</p>
            <a href="{{ $suggestionUrl('search', $opportunity->id) }}" style="display:inline-block;background:#0f172a;color:white;text-decoration:none;padding:9px 14px;border-radius:6px;">Add to content queue</a>
        </div>
    @endforeach
    @foreach ($seoOpportunities as $opportunity)
        <div style="margin-top:14px;padding:16px;border:1px solid #e2e8f0;border-radius:8px;">
            <p style="margin:0 0 4px;font-size:12px;color:#64748b;">SEO OPPORTUNITY</p><h2 style="margin:0;font-size:17px;">{{ $opportunity->title }}</h2><p style="color:#475569;">{{ $opportunity->summary }}</p>
            <a href="{{ $suggestionUrl('seo', $opportunity->id) }}" style="display:inline-block;background:#0f172a;color:white;text-decoration:none;padding:9px 14px;border-radius:6px;">Add to content queue</a>
        </div>
    @endforeach
    <p style="margin-top:20px;color:#64748b;font-size:12px;">Links expire after 30 hours and require you to be signed in to Sitewell.</p>
</div>
</body>
</html>
