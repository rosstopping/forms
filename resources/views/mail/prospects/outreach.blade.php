<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#ffffff;color:#1e293b;font-family:Arial,sans-serif;line-height:1.6;">
    <main style="max-width:620px;margin:0 auto;padding:32px 20px;">
        <section style="padding:8px 0;">
            <div style="font-size:16px;white-space:pre-line;">{{ $prospect->outreach_body }}</div>
            @if ($showcaseVideoUrl)
                <div style="margin:28px 0 0;border:1px solid #cbd5e1;border-radius:12px;background:#f8fafc;padding:20px;">
                    <p style="margin:0;font-size:18px;font-weight:700;color:#0f172a;">Your website video</p>
                    <p style="margin:6px 0 16px;font-size:14px;color:#475569;">Here’s the quick walkthrough I recorded for you.</p>
                    @if ($prospect->showcase_video_thumbnail_url)
                        <a href="{{ $showcaseVideoUrl }}" style="display:block;margin:0 0 16px;text-decoration:none;">
                            <img src="{{ $prospect->showcase_video_thumbnail_url }}" alt="Watch the website video recorded for {{ $prospect->business_name }}" width="540" style="display:block;width:100%;max-width:540px;height:auto;border:0;border-radius:8px;">
                        </a>
                    @endif
                    <a href="{{ $showcaseVideoUrl }}" style="display:inline-block;border-radius:8px;background:#0f172a;padding:12px 18px;color:#ffffff;font-weight:600;text-decoration:none;">Watch your video</a>
                    <p style="margin:14px 0 0;font-size:12px;color:#64748b;word-break:break-all;">If the button does not work, open: <a href="{{ $showcaseVideoUrl }}" style="color:#0f766e;">{{ $showcaseVideoUrl }}</a></p>
                </div>
            @endif
            <div style="margin:20px 0 0;border:1px solid #cbd5e1;border-radius:12px;padding:20px;">
                <p style="margin:0;font-size:18px;font-weight:700;color:#0f172a;">Want to have a quick chat?</p>
                <p style="margin:6px 0 16px;font-size:14px;color:#475569;">Pick any time that works for you.</p>
                <a href="https://cal.com/ross" style="display:inline-block;border-radius:8px;background:#0f766e;padding:12px 18px;color:#ffffff;font-weight:600;text-decoration:none;">Book a call with Ross</a>
                <p style="margin:14px 0 0;font-size:12px;color:#64748b;">Or visit <a href="https://cal.com/ross" style="color:#0f766e;">cal.com/ross</a></p>
            </div>
            <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#64748b;">Full disclosure, because we don’t currently manage your website, the data we can see is fairly limited. If you decided to work with us, we’d connect tools like Google Search Console, giving us a much clearer picture of how your website is actually performing.</p>
        </section>
    </main>
</body>
</html>
