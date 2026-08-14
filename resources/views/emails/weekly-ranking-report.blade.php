<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Weekly ranking report</title></head>
<body style="font-family:Arial,sans-serif;background:#f6f8fb;padding:24px;color:#111827;">
<div style="max-width:640px;margin:0 auto;background:white;border-radius:12px;padding:24px;">
    <p style="font-size:12px;text-transform:uppercase;color:#64748b;">Weekly ranking report</p>
    <h1 style="margin:4px 0 8px;font-size:24px;">{{ $website->name }}</h1>
    @if ($report['latestSearch'])
        <h2 style="margin-top:24px;font-size:18px;">Google Search performance</h2>
        <p style="color:#64748b;">Month beginning {{ $report['latestSearch']->month->format('j M Y') }}</p>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="text-align:center;">
            <tr><td style="padding:12px;background:#f8fafc;"><strong>{{ number_format($report['latestSearch']->clicks) }}</strong><br>Clicks</td><td style="padding:12px;background:#f8fafc;"><strong>{{ number_format($report['latestSearch']->impressions) }}</strong><br>Impressions</td><td style="padding:12px;background:#f8fafc;"><strong>{{ number_format($report['latestSearch']->ctr * 100, 1) }}%</strong><br>CTR</td><td style="padding:12px;background:#f8fafc;"><strong>{{ number_format($report['latestSearch']->position, 1) }}</strong><br>Position</td></tr>
        </table>
    @endif
    @if ($report['latestSeo'])
        <h2 style="margin-top:24px;font-size:18px;">Estimated rankings</h2>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="text-align:center;">
            <tr><td style="padding:12px;background:#f8fafc;"><strong>~{{ number_format($report['latestSeo']->estimated_organic_traffic) }}</strong><br>Estimated traffic</td><td style="padding:12px;background:#f8fafc;"><strong>{{ number_format($report['latestSeo']->organic_keywords) }}</strong><br>Ranking keywords</td><td style="padding:12px;background:#f8fafc;"><strong>{{ number_format($report['latestSeo']->top_10_keywords) }}</strong><br>Top 10</td></tr>
        </table>
    @endif
    @if ($report['highlights']->isNotEmpty())
        <h2 style="margin-top:24px;font-size:18px;">Highlights</h2>
        <ul style="padding-left:20px;">
            @foreach ($report['highlights'] as $highlight)
                <li style="margin:8px 0;"><strong>{{ $highlight['label'] }}</strong>: {{ $highlight['change'] }} — {{ $highlight['direction'] }}</li>
            @endforeach
        </ul>
    @else
        <p style="margin-top:24px;color:#64748b;">More comparable observations are needed before Sitewell can identify ranking trends.</p>
    @endif
    @if ($report['opportunities']->isNotEmpty())
        <h2 style="margin-top:24px;font-size:18px;">Worth working on</h2>
        @foreach ($report['opportunities'] as $opportunity)
            <div style="margin-top:10px;padding:12px;border:1px solid #e2e8f0;border-radius:8px;"><strong>{{ $opportunity->title }}</strong><p style="margin:5px 0 0;color:#475569;">{{ $opportunity->summary }}</p></div>
        @endforeach
    @endif
    <p style="margin-top:24px;"><a href="{{ $reportUrl }}" style="display:inline-block;background:#0f172a;color:white;text-decoration:none;padding:10px 16px;border-radius:6px;">View ranking performance</a></p>
</div>
</body>
</html>
