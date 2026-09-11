<x-email-layout :linked="false">
<div>
<div style="font-size:16px;white-space:pre-line;">{{ $messageBody }}</div>
@if ($auditReportUrl)
<div style="margin:28px 0 0;border:1px solid #dce3dd;border-radius:12px;background:#eef3ec;padding:20px;">
<p style="margin:0;font-size:18px;font-weight:600;color:#315a46;">Your website audit</p>
<p style="margin:6px 0 16px;font-size:14px;color:#59685f;">See the public website checks and opportunities behind this email.</p>
<a href="{{ $auditReportUrl }}" class="button {{ $showcaseVideoUrl ? 'button-secondary' : 'button-primary' }}">View your website audit</a>
<p style="margin:14px 0 0;font-size:14px;color:#59685f;">This private link is available for 30 days.</p>
</div>
@endif
@if ($showcaseVideoUrl)
<div style="margin:28px 0 0;border:1px solid #dce3dd;border-radius:12px;background:#eef3ec;padding:20px;">
<p style="margin:0;font-size:18px;font-weight:600;color:#17201d;">Your website video</p>
<p style="margin:6px 0 16px;font-size:14px;color:#59685f;">Here’s the quick walkthrough I recorded for you.</p>
@if ($prospect->showcase_video_thumbnail_url)
<a href="{{ $showcaseVideoUrl }}" style="display:block;margin:0 0 16px;text-decoration:none;">
<img src="{{ $prospect->showcase_video_thumbnail_url }}" alt="Watch the website video recorded for {{ $prospect->business_name }}" width="540" style="display:block;width:100%;max-width:540px;height:auto;border:0;border-radius:8px;">
</a>
@endif
<a href="{{ $showcaseVideoUrl }}" style="display:inline-block;border-radius:8px;background:#167a53;padding:12px 18px;color:#ffffff;font-weight:600;text-decoration:none;">Watch your video</a>
<p style="margin:14px 0 0;font-size:14px;color:#59685f;word-break:break-all;">If the button does not work, open: <a href="{{ $showcaseVideoUrl }}" style="color:#167a53;">{{ $showcaseVideoUrl }}</a></p>
</div>
@endif
@if ($bookingUrl)<div style="margin:20px 0 0;border:1px solid #dce3dd;border-radius:12px;padding:20px;">
<p style="margin:0;font-size:18px;font-weight:600;color:#17201d;">Want to have a quick chat?</p>
<p style="margin:6px 0 16px;font-size:14px;color:#59685f;">Pick any time that works for you.</p>
<a href="{{ $bookingUrl }}" style="display:inline-block;border-radius:8px;background:#dce9d9;padding:12px 18px;color:#315a46;font-weight:600;text-decoration:none;">Book a call with Ross</a>
<p style="margin:14px 0 0;font-size:14px;color:#59685f;">Or use this <a href="{{ $bookingUrl }}" style="color:#167a53;">booking link</a></p>
</div>@endif
@if ($showOutreachDisclosure)
<p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:#59685f;">Full disclosure, because we don’t currently manage your website, the data we can see is fairly limited. If you decided to work with us, we’d connect tools like Google Search Console, giving us a much clearer picture of how your website is actually performing.</p>
@endif
</div>
@if ($trackingOpenUrl)
<img src="{{ $trackingOpenUrl }}" alt="" width="1" height="1" style="display:block;width:1px;height:1px;border:0;" aria-hidden="true">
@endif
</x-email-layout>
