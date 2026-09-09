@props(['url'])
<tr>
<td class="header" bgcolor="#17201d">
@if ($url)<a href="{{ $url }}" class="brand">Sitewell<span class="brand-dot">.</span></a>
@else<span class="brand">Sitewell<span class="brand-dot">.</span></span>@endif
<p class="brand-tagline">Your website, well looked after.</p>
</td>
</tr>
