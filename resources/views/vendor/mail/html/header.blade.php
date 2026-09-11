@props(['url', 'brandName' => null])
<tr>
<td class="header" bgcolor="#17201d">
@if ($brandName !== null)
<span class="brand">{{ $brandName }}</span>
@else
@if ($url)<a href="{{ $url }}" class="brand">Sitewell<span class="brand-dot">.</span></a>
@else<span class="brand">Sitewell<span class="brand-dot">.</span></span>@endif
<p class="brand-tagline">Your website, well looked after.</p>
@endif
</td>
</tr>
