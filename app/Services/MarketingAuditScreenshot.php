<?php

namespace App\Services;

use App\Models\WebsiteAudit;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Throwable;

class MarketingAuditScreenshot
{
    public function capture(WebsiteAudit $audit): void
    {
        if (! $this->hasPublicHost($audit->website_url)) {
            return;
        }

        try {
            $browser = Browsershot::url($audit->website_url)
                ->setNodeModulePath(base_path('node_modules'))
                ->windowSize(1024, 640)
                ->setScreenshotType('jpeg', 68)
                ->disableRedirects()
                ->disableCaptureURLS()
                ->dismissDialogs()
                ->setDelay(1200)
                ->timeout(15);

            $chromePath = config('marketing.audit_screenshot.chrome_path');
            if (is_string($chromePath) && $chromePath !== '') {
                $browser->setChromePath($chromePath);
            } elseif (app()->environment('local') && is_file('/Applications/Brave Browser.app/Contents/MacOS/Brave Browser')) {
                $browser->setChromePath('/Applications/Brave Browser.app/Contents/MacOS/Brave Browser');
            }

            $image = $browser->screenshot();
            if (strlen($image) > 1_000_000 || ! str_starts_with($image, "\xFF\xD8\xFF")) {
                return;
            }

            Storage::disk('local')->put($this->pathFor($audit), $image);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function pathFor(WebsiteAudit $audit): string
    {
        return 'website-audit-previews/'.$audit->public_id.'.jpg';
    }

    private function hasPublicHost(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)
            || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            return false;
        }

        $host = $parts['host'];
        if (! is_string($host)
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || $host === 'localhost'
            || str_ends_with($host, '.localhost')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (app()->environment('testing')) {
            return true;
        }

        $addresses = gethostbynamel($host);

        return $addresses !== false && $addresses !== [] && collect($addresses)->every(
            fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false,
        );
    }
}
