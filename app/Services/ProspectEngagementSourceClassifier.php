<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProspectEngagementSourceClassifier
{
    public function classify(Request $request): string
    {
        foreach (config('outreach.scanner_detection.headers', []) as $header => $scannerValues) {
            $value = Str::lower((string) $request->header($header));

            if ($value !== '' && collect($scannerValues)->contains(fn (string $scannerValue): bool => Str::contains($value, Str::lower($scannerValue)))) {
                return 'scanner';
            }
        }

        $userAgent = Str::lower((string) $request->userAgent());

        if ($userAgent !== '' && collect(config('outreach.scanner_detection.user_agent_patterns', []))
            ->contains(fn (string $pattern): bool => Str::contains($userAgent, Str::lower($pattern)))) {
            return 'scanner';
        }

        return 'tracking';
    }
}
