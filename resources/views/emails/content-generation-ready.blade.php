<x-email-layout>
<p style="font-size:14px;font-weight:600;color:#d63d24;">Automatic content run</p>
<h1 style="margin:4px 0 8px;font-size:24px;">Content is ready for review</h1>
<p>Sitewell completed its scheduled content work for <strong>{{ $generation->plan->website->name }}</strong> and opened pull request #{{ $generation->pull_request_number }}.</p>
@if ($generation->contentRequests->isNotEmpty())
<h2 style="margin-top:24px;font-size:18px;">What Sitewell worked on</h2>
@foreach ($generation->contentRequests as $request)
<div style="margin-top:10px;padding:12px;border:1px solid #ebe6e2;border-radius:8px;">
<p style="margin:0;white-space:pre-line;">{{ $request->instructions }}</p>
@if ($request->searchOpportunity)
<p style="margin:7px 0 0;color:#62666d;font-size:14px;">Source: Search Console suggestion — {{ $request->searchOpportunity->title }}</p>
@elseif ($request->seoOpportunity)
<p style="margin:7px 0 0;color:#62666d;font-size:14px;">Source: SEO Intelligence — {{ $request->seoOpportunity->title }}</p>
@else
<p style="margin:7px 0 0;color:#62666d;font-size:14px;">Source: content todo</p>
@endif
</div>
@endforeach
@else
<p style="color:#62666d;">No queued content request was attached, so Sitewell used the current Search Console performance and content-plan guidance.</p>
@endif
<p style="margin-top:20px;color:#62666d;">Nothing has been published automatically. Review and merge the pull request when you are happy with the changes.</p>
<p style="margin-top:24px;"><a href="{{ $generation->pull_request_url }}" class="button button-primary">Review pull request</a></p>
</x-email-layout>
