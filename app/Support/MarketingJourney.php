<?php

namespace App\Support;

use App\Events\MarketingConversionRecorded;
use App\Models\MarketingConversion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketingJourney
{
    public const PARAMETERS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'gclid', 'gbraid', 'wbraid'];

    /** @return array<string, mixed> */
    public function capture(Request $request, ?string $landing = null): array
    {
        $attribution = $request->session()->get('marketing_attribution', []);
        $campaign = [];

        foreach (self::PARAMETERS as $key) {
            $value = $request->query($key);
            if (is_string($value) && preg_match('/\A[\pL\pN _.,~:+\/\-]{1,255}\z/u', $value)) {
                $campaign[$key] = $value;
            }
        }

        if ($attribution === [] || ($attribution['expires_at'] ?? 0) < now()->timestamp) {
            $attribution = [
                'journey_id' => (string) Str::uuid(),
                'landing_page' => $landing ?? $request->path(),
                'first_touch' => $campaign,
                'last_touch' => $campaign,
                'expires_at' => now()->addDays(30)->timestamp,
            ];
        } elseif ($campaign !== []) {
            $attribution['last_touch'] = $campaign;
        }

        $request->session()->put('marketing_attribution', $attribution);

        return $attribution;
    }

    /** @param array<string, mixed> $attribution */
    public function record(string $name, string $key, array $attribution): MarketingConversion
    {
        $conversion = MarketingConversion::query()->firstOrCreate([
            'deduplication_key' => hash('sha256', $name.':'.$key),
        ], [
            'event_id' => (string) Str::uuid(),
            'name' => $name,
            'attribution' => $attribution,
        ]);

        if ($conversion->wasRecentlyCreated) {
            MarketingConversionRecorded::dispatch($conversion);
        }

        return $conversion;
    }
}
