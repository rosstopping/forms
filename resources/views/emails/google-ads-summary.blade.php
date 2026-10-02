@if ($adsSummary)
<h2 style="margin-top:24px;font-size:18px;">Google Ads</h2>
<p style="color:#62666d;">Enabled campaigns · {{ $adsSummary['start']->format('j M') }}–{{ $adsSummary['end']->format('j M Y') }}</p>
<table class="metrics" role="presentation" width="100%" cellspacing="0" cellpadding="0" style="text-align:center;">
<tr><td style="padding:12px;background:#faf7f4;"><strong>{{ number_format($adsSummary['impressions']) }}</strong><br>Impressions</td><td style="padding:12px;background:#faf7f4;"><strong>{{ number_format($adsSummary['clicks']) }}</strong><br>Clicks</td><td style="padding:12px;background:#faf7f4;"><strong>{{ $adsSummary['currency'] }} {{ number_format($adsSummary['cost_micros'] / 1000000, 2) }}</strong><br>Spend</td><td style="padding:12px;background:#faf7f4;"><strong>{{ number_format($adsSummary['conversions'], 1) }}</strong><br>Conversions</td></tr>
</table>
@endif
