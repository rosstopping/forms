<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Monthly search performance</title></head>
<body style="font-family:Arial,sans-serif;background:#f6f8fb;padding:24px;color:#111827;">
<div style="max-width:640px;margin:0 auto;background:white;border-radius:12px;padding:24px;">
    <p style="font-size:12px;text-transform:uppercase;color:#64748b;">Monthly search performance</p>
    <h1 style="margin:4px 0 8px;font-size:24px;">{{ $website->name }}</h1>
    @if ($report['latestSearch'])
        <p style="color:#64748b;">Your results for {{ $report['latestSearch']->month->format('F Y') }}, compared with {{ $report['previousSearch']?->month->format('F Y') ?? 'the previous available month' }}.</p>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="text-align:center;margin-top:20px;">
            <tr><td style="padding:12px;background:#f8fafc;"><strong>{{ number_format($report['latestSearch']->clicks) }}</strong><br>Google clicks</td><td style="padding:12px;background:#f8fafc;"><strong>{{ number_format($report['latestSearch']->impressions) }}</strong><br>Impressions</td><td style="padding:12px;background:#f8fafc;"><strong>{{ number_format($report['latestSearch']->ctr * 100, 1) }}%</strong><br>CTR</td><td style="padding:12px;background:#f8fafc;"><strong>{{ number_format($report['latestSearch']->position, 1) }}</strong><br>Avg. position</td></tr>
        </table>
    @else
        <p style="color:#64748b;">We need two complete months of connected Google Search Console data before we can show your month-on-month search performance.</p>
    @endif
    @if ($report['highlights']->isNotEmpty())
        <h2 style="margin-top:24px;font-size:18px;">What changed</h2>
        <ul style="padding-left:20px;">
            @foreach ($report['highlights'] as $highlight)
                <li style="margin:8px 0;"><strong>{{ $highlight['label'] }}</strong>: {{ $highlight['change'] }} — {{ $highlight['direction'] }}</li>
            @endforeach
        </ul>
    @endif
    @if ($report['queryWins']->isNotEmpty())
        <h2 style="margin-top:24px;font-size:18px;">Searches moving in the right direction</h2>
        @foreach ($report['queryWins'] as $movement)
            <div style="margin-top:10px;padding:12px;border:1px solid #d1fae5;border-radius:8px;"><strong>{{ $movement['query'] }}</strong><p style="margin:5px 0 0;color:#475569;">Average position {{ number_format($movement['position'], 1) }} @if (abs($movement['position_change']) >= 0.1) · moved up {{ number_format($movement['position_change'], 1) }} places @endif @if (abs($movement['click_change']) >= 0.01) · {{ $movement['click_change'] > 0 ? '+' : '' }}{{ number_format($movement['click_change']) }} clicks @endif</p></div>
        @endforeach
    @endif
    @if ($report['queryDeclines']->isNotEmpty())
        <h2 style="margin-top:24px;font-size:18px;">Worth watching</h2>
        @foreach ($report['queryDeclines'] as $movement)
            <div style="margin-top:10px;padding:12px;border:1px solid #fde68a;border-radius:8px;"><strong>{{ $movement['query'] }}</strong><p style="margin:5px 0 0;color:#475569;">Average position {{ number_format($movement['position'], 1) }} @if (abs($movement['position_change']) >= 0.1) · moved down {{ number_format(abs($movement['position_change']), 1) }} places @endif @if (abs($movement['click_change']) >= 0.01) · {{ $movement['click_change'] > 0 ? '+' : '' }}{{ number_format($movement['click_change']) }} clicks @endif</p></div>
        @endforeach
    @endif
    @if ($report['latestSeo'])
        <h2 style="margin-top:24px;font-size:18px;">Estimated search visibility</h2>
        <p style="color:#64748b;">These are third-party estimates, shown separately from your verified Google Search Console results.</p>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="text-align:center;">
            <tr><td style="padding:12px;background:#f8fafc;"><strong>~{{ number_format($report['latestSeo']->estimated_organic_traffic) }}</strong><br>Estimated traffic</td><td style="padding:12px;background:#f8fafc;"><strong>{{ number_format($report['latestSeo']->organic_keywords) }}</strong><br>Ranking keywords</td><td style="padding:12px;background:#f8fafc;"><strong>{{ number_format($report['latestSeo']->top_10_keywords) }}</strong><br>Top 10</td></tr>
        </table>
    @endif
    <p style="margin-top:24px;"><a href="{{ $reportUrl }}" style="display:inline-block;background:#0f172a;color:white;text-decoration:none;padding:10px 16px;border-radius:6px;">View search performance</a></p>
</div>
</body>
</html>
