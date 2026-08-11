<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#f8fafc;color:#1e293b;font-family:Arial,sans-serif;line-height:1.6;">
    <main style="max-width:620px;margin:0 auto;padding:32px 20px;">
        <section style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:32px;">
            <p style="margin:0 0 20px;font-size:14px;font-weight:600;color:#0f766e;">A quick website review</p>
            <div style="font-size:16px;white-space:pre-line;">{{ $prospect->outreach_body }}</div>
            <p style="margin:28px 0 0;">
                <a href="{{ $reportUrl }}" style="display:inline-block;border-radius:8px;background:#0f172a;padding:12px 18px;color:#ffffff;font-weight:600;text-decoration:none;">View your website review</a>
            </p>
            <p style="margin:18px 0 0;font-size:13px;color:#64748b;">The review summarises a few publicly visible website checks. This private link expires in 30 days and does not require a login.</p>
        </section>
    </main>
</body>
</html>
