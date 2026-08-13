<?php

namespace App\Services;

use App\Models\Website;

class PixelInstallationSnippet
{
    public function for(Website $website): string
    {
        $assetUrl = (string) config('services.sitewell.pixel_asset_url');
        $apiUrl = (string) config('services.sitewell.pixel_api_url');

        return implode("\n", [
            '<script',
            '    async',
            '    src="'.$assetUrl.'"',
            '    data-site="'.$website->pixel_public_key.'"',
            '    data-api="'.$apiUrl.'"',
            '></script>',
        ]);
    }
}
