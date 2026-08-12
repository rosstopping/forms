<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#f8fafc;color:#1e293b;font-family:Arial,sans-serif;line-height:1.6;">
    <main style="max-width:620px;margin:0 auto;padding:32px 20px;">
        <section style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:32px;">
            <p style="margin:0 0 20px;font-size:14px;font-weight:600;color:#0f766e;">{{ $prospect->website_url ? 'A quick website review' : 'A website idea for your business' }}</p>
            <div style="font-size:16px;white-space:pre-line;">{{ $prospect->outreach_body }}</div>
            @if ($reportUrl)
                <p style="margin:28px 0 0;">
                    <a href="{{ $reportUrl }}" style="display:inline-block;border-radius:8px;background:#0f172a;padding:12px 18px;color:#ffffff;font-weight:600;text-decoration:none;">View your website review</a>
                </p>
                <p style="margin:18px 0 0;font-size:13px;color:#64748b;">The review summarises a few publicly visible website checks. This private link expires in 30 days and does not require a login.</p>
            @endif
        </section>
        <section style="margin-top:16px;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:28px 32px;">
            <h2 style="margin:0;font-size:18px;line-height:1.3;color:#0f172a;">What is Sitewell?</h2>
            <p style="margin:10px 0 0;font-size:14px;color:#475569;">Sitewell is a website care platform for businesses that want their website to stay healthy, visible, and better at turning visits into enquiries.</p>
            <ul style="margin:18px 0 0;padding-left:20px;font-size:14px;color:#334155;">
                @unless ($prospect->website_url)<li style="margin-top:8px;">A professionally built website included for businesses that need one</li>@endunless
                <li style="margin-top:8px;">Website health checks, clear reports, and practical fixes</li>
                <li style="margin-top:8px;">Forms, automatic acknowledgements, and a simple lead CRM</li>
                <li style="margin-top:8px;">Google Search Console insights and reviewable content improvements</li>
                <li style="margin-top:8px;">Google Business Profile posts and review replies on the complete plan</li>
            </ul>
        </section>
    </main>
</body>
</html>
