<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Content ready for review</title></head>
<body style="font-family:Arial,sans-serif;background:#f6f8fb;padding:24px;color:#111827;">
<div style="max-width:640px;margin:0 auto;background:white;border-radius:12px;padding:24px;">
    <p style="font-size:12px;text-transform:uppercase;color:#64748b;">Automatic content run</p>
    <h1 style="margin:4px 0 8px;font-size:24px;">Content is ready for review</h1>
    <p>Sitewell completed its scheduled content work for <strong>{{ $generation->plan->website->name }}</strong> and opened pull request #{{ $generation->pull_request_number }}.</p>
    @if ($generation->contentRequests->isNotEmpty())
        <h2 style="margin-top:24px;font-size:18px;">What Sitewell worked on</h2>
        @foreach ($generation->contentRequests as $request)
            <div style="margin-top:10px;padding:12px;border:1px solid #e2e8f0;border-radius:8px;">
                <p style="margin:0;white-space:pre-line;">{{ $request->instructions }}</p>
                @if ($request->searchOpportunity)
                    <p style="margin:7px 0 0;color:#64748b;font-size:13px;">Source: Search Console suggestion — {{ $request->searchOpportunity->title }}</p>
                @elseif ($request->seoOpportunity)
                    <p style="margin:7px 0 0;color:#64748b;font-size:13px;">Source: SEO Intelligence — {{ $request->seoOpportunity->title }}</p>
                @else
                    <p style="margin:7px 0 0;color:#64748b;font-size:13px;">Source: content todo</p>
                @endif
            </div>
        @endforeach
    @else
        <p style="color:#64748b;">No queued content todo was attached, so Copilot used the current Search Console performance and content-plan guidance.</p>
    @endif
    <p style="margin-top:20px;color:#475569;">Nothing has been published automatically. Review and merge the pull request when you are happy with the changes.</p>
    <p style="margin-top:24px;"><a href="{{ $generation->pull_request_url }}" style="display:inline-block;background:#0f172a;color:white;text-decoration:none;padding:10px 16px;border-radius:6px;">Review pull request</a></p>
</div>
</body>
</html>
