<x-email-layout :linked="false">
<div>
<div style="font-size:16px;white-space:pre-line;">{{ $messageBody }}</div>
@if ($showcaseVideoUrl)
<div style="margin:28px 0 0;border:1px solid #ebe6e2;border-radius:12px;background:#faf7f4;padding:20px;">
@if ($prospect->showcase_video_thumbnail_url)
<a href="{{ $showcaseVideoUrl }}" style="display:block;margin:0 0 16px;text-decoration:none;">
<img src="{{ $prospect->showcase_video_thumbnail_url }}" alt="Watch the website video recorded for {{ $prospect->business_name }}" width="540" style="display:block;width:100%;max-width:540px;height:auto;border:0;border-radius:8px;">
</a>
@endif
<a href="{{ $showcaseVideoUrl }}" style="display:inline-block;border-radius:999px;background:#d63d24;padding:12px 18px;color:#ffffff;font-weight:600;text-decoration:none;">Watch your video</a>
<p style="margin:14px 0 0;font-size:14px;color:#62666d;word-break:break-all;">If the button does not work, open: <a href="{{ $showcaseVideoUrl }}" style="color:#d63d24;">{{ $showcaseVideoUrl }}</a></p>
</div>
@endif
@if ($auditReportUrl && $isInitialOutreach)
<x-prospect-audit-block :url="$auditReportUrl" :has-video="(bool) $showcaseVideoUrl" />
@endif
@if ($bookingUrl)<div style="margin:20px 0 0;border:1px solid #ebe6e2;border-radius:12px;padding:20px;">
<p style="margin:0;font-size:18px;font-weight:600;color:#151618;">Want to have a quick chat?</p>
<p style="margin:6px 0 16px;font-size:14px;color:#62666d;">Pick any time that works for you.</p>
<a href="{{ $bookingUrl }}" style="display:inline-block;border-radius:999px;background:#faf7f4;padding:12px 18px;color:#151618;font-weight:600;text-decoration:none;">Book a call with Ross</a>
<p style="margin:14px 0 0;font-size:14px;color:#62666d;">Or use this <a href="{{ $bookingUrl }}" style="color:#d63d24;">booking link</a></p>
<p style="margin:14px 0 0;font-size:14px;color:#62666d;">You can also call me on <a href="tel:+441302248374" style="color:#d63d24;">01302 248 374</a>.</p>
</div>@endif
@if ($auditReportUrl && ! $isInitialOutreach)
<x-prospect-audit-block :url="$auditReportUrl" :has-video="(bool) $showcaseVideoUrl" />
@endif
@if ($showOutreachDisclosure)
<p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:#62666d;">Full disclosure, because we don’t currently manage your website, the data we can see is fairly limited. If you decided to work with us, we’d connect tools like Google Search Console, giving us a much clearer picture of how your website is actually performing.</p>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0 0;border-top:1px solid #ebe6e2;">
<tr><td style="padding:16px 0 0;">
<a href="https://digizu.co.uk" style="display:inline-block;text-decoration:none;"><img src="https://digizu.co.uk/assets/images/logo-web.png" alt="Digizu" width="90" style="display:block;width:90px;max-width:100%;height:auto;border:0;"></a>
<p style="margin:8px 0 0;font-size:12px;line-height:1.6;color:#62666d;">We’re Digizu, a local web development agency based in Doncaster, helping businesses build and improve their websites. <a href="https://digizu.co.uk" style="color:#62666d;text-decoration:underline;">digizu.co.uk</a></p>
</td></tr>
</table>
<p style="margin:20px 0 0;font-size:12px;line-height:1.6;color:#62666d;"><a href="{{ $unsubscribeUrl }}" style="color:#62666d;text-decoration:underline;">Unsubscribe</a></p>
</div>
@if ($trackingOpenUrl)
<img src="{{ $trackingOpenUrl }}" alt="" width="1" height="1" style="display:block;width:1px;height:1px;border:0;" aria-hidden="true">
@endif
</x-email-layout>
