<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#ffffff;color:#1e293b;font-family:Arial,sans-serif;line-height:1.6;">
    <main style="max-width:620px;margin:0 auto;padding:32px 20px;">
        <section style="padding:8px 0;">
            <div style="font-size:16px;white-space:pre-line;">{{ $prospect->outreach_body }}</div>
            @if ($showcaseVideoUrl)
                <p style="margin:28px 0 0;">
                    <a href="{{ $showcaseVideoUrl }}" style="display:inline-block;border-radius:8px;background:#0f172a;padding:12px 18px;color:#ffffff;font-weight:600;text-decoration:none;">Watch the quick video</a>
                </p>
            @endif
        </section>
    </main>
</body>
</html>
