<?php

namespace App\Services;

use App\Models\AiVisibilitySetting;
use App\Models\Website;
use Illuminate\Support\Str;

class AiVisibilitySetup
{
    public function settings(Website $website): AiVisibilitySetting
    {
        $settings = AiVisibilitySetting::query()->where('website_id', $website->id)->first()
            ?? new AiVisibilitySetting(['website_id' => $website->id]);
        $connection = $website->businessProfileConnection;
        $snapshot = $connection?->audits()->where('status', 'completed')->latest('completed_at')->first()?->snapshot ?? [];

        if (blank($settings->brand_name)) {
            $settings->brand_name = Str::limit($connection?->location_title ?: $website->name, 150, '');
        }
        if (empty($settings->services)) {
            $settings->services = collect([
                data_get($snapshot, 'categories.primaryCategory.displayName'),
                ...(array) data_get($snapshot, 'categories.additionalCategories.*.displayName', []),
            ])->filter(fn ($value) => is_string($value) && filled($value))->map(fn ($value) => Str::limit($value, 180, ''))->unique()->take(15)->values()->all();
        }
        if (empty($settings->locations)) {
            $locality = data_get($snapshot, 'storefrontAddress.locality');
            $settings->locations = is_string($locality) && filled($locality) ? [Str::limit($locality, 180, '')] : [];
        }
        $settings->aliases ??= [];
        $settings->frequency_days = in_array($settings->frequency_days, [7, 14, 28], true) ? $settings->frequency_days : 7;
        $settings->providers = ['openai'];

        return $settings;
    }
}
