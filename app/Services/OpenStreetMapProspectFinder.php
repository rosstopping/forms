<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenStreetMapProspectFinder
{
    public const BUSINESS_TYPES = [
        'tradespeople' => 'Tradespeople',
        'beauty_wellness' => 'Beauty & wellness',
        'restaurants_cafes' => 'Restaurants & cafés',
        'estate_agents' => 'Estate agents',
        'accountants' => 'Accountants',
        'dental_practices' => 'Dental practices',
        'gyms' => 'Gyms & fitness',
    ];

    /** @return array<int, array<string, mixed>> */
    public function find(string $area, string $businessType): array
    {
        $key = 'osm-prospect-finder:'.sha1($area.'|'.$businessType);

        return Cache::remember($key, now()->addDays(7), fn (): array => $this->request($area, $businessType));
    }

    /** @return array<int, array<string, mixed>> */
    protected function request(string $area, string $businessType): array
    {
        $response = Http::asForm()->acceptJson()->withUserAgent(config('app.name').' Prospect Finder')
            ->connectTimeout(5)->timeout(30)->retry([500, 1500], throw: false)
            ->post('https://overpass-api.de/api/interpreter', ['data' => $this->query($area, $businessType)])->throw();

        return collect($response->json('elements', []))
            ->map(fn (array $element): ?array => $this->candidate($element))
            ->filter()->unique('source_key')->take(50)->values()->all();
    }

    protected function query(string $area, string $businessType): string
    {
        $tags = match ($businessType) {
            'tradespeople' => ['["craft"~"^(builder|carpenter|electrician|plumber|roofer)$"]'],
            'beauty_wellness' => ['["shop"~"^(beauty|hairdresser)$"]', '["amenity"~"^(beauty_salon|spa)$"]'],
            'restaurants_cafes' => ['["amenity"~"^(cafe|restaurant)$"]'],
            'estate_agents' => ['["office"="estate_agent"]'],
            'accountants' => ['["office"="accountant"]'],
            'dental_practices' => ['["amenity"="dentist"]'],
            'gyms' => ['["leisure"="fitness_centre"]', '["amenity"="gym"]'],
        };
        $conditions = collect($tags)->map(fn (string $tag): string => "nwr[\"name\"]{$tag}[\"website\"](area.searchArea);");
        $safeArea = str_replace(['\\', '"'], ['\\\\', '\\"'], $area);

        return "[out:json][timeout:25];\narea[\"name\"=\"{$safeArea}\"][\"boundary\"=\"administrative\"]->.searchArea;\n(\n"
            .$conditions->implode("\n")."\n);\nout center tags;";
    }

    /** @param array<string, mixed> $element
     * @return array<string, mixed>|null
     */
    protected function candidate(array $element): ?array
    {
        $tags = Arr::get($element, 'tags', []);
        $website = $this->website(Arr::get($tags, 'website') ?: Arr::get($tags, 'contact:website'));

        if (! is_array($tags) || ! $website || ! is_string(Arr::get($tags, 'name'))) {
            return null;
        }

        $address = collect(['addr:housenumber', 'addr:street', 'addr:city', 'addr:postcode'])
            ->map(fn (string $key): mixed => Arr::get($tags, $key))
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')->implode(', ');

        return [
            'source_key' => Arr::get($element, 'type').'/'.Arr::get($element, 'id'),
            'business_name' => Str::squish(Arr::get($tags, 'name')),
            'website_url' => $website,
            'phone' => Arr::get($tags, 'phone') ?: Arr::get($tags, 'contact:phone'),
            'address' => $address ?: null,
            'source_data' => ['tags' => $tags],
        ];
    }

    protected function website(mixed $website): ?string
    {
        if (! is_string($website) || $website === '') {
            return null;
        }

        $website = Str::startsWith($website, ['http://', 'https://']) ? $website : 'https://'.$website;

        return filter_var($website, FILTER_VALIDATE_URL) ? rtrim($website, '/') : null;
    }
}
