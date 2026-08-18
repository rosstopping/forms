<?php

namespace App\Services\DataForSEO;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DataForSEOLocationResolver
{
    public const ENDPOINT = 'serp/google/locations/gb';

    public function __construct(private DataForSEOClient $client) {}

    public function resolve(string $location): int
    {
        $needle = Str::lower(Str::squish($location));
        $locations = collect(Cache::remember(
            'dataforseo:serp:google-locations:gb:v1',
            now()->addDays(30),
            fn (): array => $this->client->get(self::ENDPOINT)->results,
        ));

        $match = $locations->first(fn (mixed $item): bool => is_array($item) && Str::lower((string) Arr::get($item, 'location_name')) === $needle)
            ?? $locations
                ->filter(fn (mixed $item): bool => is_array($item) && Str::lower(Str::before((string) Arr::get($item, 'location_name'), ',')) === $needle)
                ->sortBy(fn (array $item): string => $this->locationPriority($item).':'.mb_strlen((string) Arr::get($item, 'location_name')))
                ->first();

        $code = is_array($match) ? Arr::get($match, 'location_code') : null;

        if (! is_numeric($code)) {
            throw new InvalidArgumentException("DataForSEO does not recognise the UK location '{$location}'. Try a nearby town, city, or the full place name.");
        }

        return (int) $code;
    }

    /** @param array<string, mixed> $location */
    private function locationPriority(array $location): string
    {
        return match (Str::lower((string) Arr::get($location, 'location_type'))) {
            'city' => '0',
            'municipality' => '1',
            'county' => '2',
            default => '3',
        };
    }
}
