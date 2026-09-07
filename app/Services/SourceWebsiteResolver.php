<?php

namespace App\Services;

use App\Models\Website;
use App\Models\WebsiteDomain;
use Illuminate\Http\Request;

class SourceWebsiteResolver
{
    public function resolve(Request $request): ?Website
    {
        $source = $request->header('origin') ?: $request->header('referer');

        if (blank($source)) {
            return null;
        }

        $url = parse_url($source);

        if ($url === false || ! isset($url['scheme'], $url['host']) || ! in_array(strtolower($url['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($url['host']);
        $host = preg_replace('/^www\./i', '', $host);
        $host = idn_to_utf8($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;

        if ($host === '') {
            return null;
        }

        return Website::query()
            ->whereHas('domains', fn ($query) => $query
                ->where('domain', $host)
                ->where('ownership_status', WebsiteDomain::OWNERSHIP_VERIFIED))
            ->first();
    }
}
