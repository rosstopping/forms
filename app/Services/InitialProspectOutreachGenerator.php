<?php

namespace App\Services;

use App\Models\Prospect;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class InitialProspectOutreachGenerator
{
    /** @return array{subject: string, body: string}|null */
    public function generate(Prospect $prospect): ?array
    {
        $context = $this->context($prospect);

        if ($context === null) {
            return null;
        }

        $contactName = filled($prospect->contact_name) ? $prospect->contact_name : null;
        $greeting = $contactName ? 'Hi '.$contactName.',' : 'Hi,';
        $variation = abs(crc32($prospect->business_name.'|'.$context['keyword'])) % 3;
        $websiteObservation = match ($variation) {
            0 => 'I had a look at the website and think I can see a few things that could be contributing to that.',
            1 => 'I took a look at the website and noticed a few things that might help explain it.',
            default => 'I had a quick look through the website and there are a few things that could potentially explain why.',
        };
        $credibility = match ($variation) {
            0 => 'I’m a web developer, so this is the sort of thing I work on all the time.',
            1 => 'I work as a web developer, so I spend a lot of time looking at this sort of thing.',
            default => 'I’m a web developer and this is the kind of thing I deal with regularly.',
        };
        $question = match ($variation) {
            0 => 'Happy to send over what I noticed if you’re interested.',
            1 => 'Would you like me to send over what I noticed?',
            default => 'If it would be useful, shall I send over the things I noticed?',
        };

        return [
            'subject' => Str::limit($context['service'], 60, ''),
            'body' => implode("\n\n", [
                $greeting,
                $this->opening($prospect, $context['service'], $context['location']),
                $this->visibility($context['position']).' '.$websiteObservation,
                $credibility.' '.$question,
                "Cheers,\nRoss",
            ]),
        ];
    }

    /** @return array{keyword: string, service: string, location: string|null, position: int}|null */
    private function context(Prospect $prospect): ?array
    {
        $activity = $prospect->activities()->where('type', 'seo_opportunity_imported')->latest()->first();
        $ranking = collect(data_get($activity?->metadata, 'rankings', []))
            ->filter(fn (mixed $ranking): bool => is_array($ranking) && filled(Arr::get($ranking, 'keyword')) && is_numeric(Arr::get($ranking, 'position')))
            ->sortBy(fn (array $ranking): int => (int) $ranking['position'])
            ->first();
        $keyword = (string) ($ranking['keyword'] ?? data_get($prospect->prospecting_context, 'search_query', ''));
        $position = $ranking['position'] ?? data_get($prospect->prospecting_context, 'google_position');

        if (blank($keyword) || ! is_numeric($position)) {
            return null;
        }

        $location = data_get($activity?->metadata, 'location')
            ?: data_get($prospect->prospecting_context, 'location')
            ?: $prospect->prospectingLocation?->name;
        $service = $this->serviceFromKeyword($keyword, is_string($location) ? $location : null);

        if (blank($service)) {
            return null;
        }

        return [
            'keyword' => Str::squish($keyword),
            'service' => $service,
            'location' => filled($location) ? Str::squish((string) $location) : null,
            'position' => max(1, (int) $position),
        ];
    }

    private function serviceFromKeyword(string $keyword, ?string $location): string
    {
        $service = Str::of($keyword)->lower()->squish();

        if (filled($location)) {
            $service = $service->replaceMatches('/\b'.preg_quote(Str::lower($location), '/').'\b/u', '')->squish();
        }

        $service = $service
            ->replaceMatches('/\b(?:near me|in|around)\b.*$/u', '')
            ->replaceMatches('/\b(?:uk|england|yorkshire)\b$/u', '')
            ->trim(' ,-');

        return $service->toString() === 'boiler repair' ? 'boiler repairs' : $service->toString();
    }

    private function opening(Prospect $prospect, string $service, ?string $location): string
    {
        $search = $location ? $service.' in '.$location : $service;

        return match (abs(crc32($prospect->business_name.'|opening')) % 3) {
            0 => "I found {$prospect->business_name} while searching for {$search} earlier.",
            1 => "I came across {$prospect->business_name} when I was looking at {$service}".($location ? " around {$location}" : '').'.',
            default => "I was looking through {$service} businesses".($location ? " in {$location}" : '')." and came across {$prospect->business_name}.",
        };
    }

    private function visibility(int $position): string
    {
        return match (true) {
            $position <= 3 => 'You’re already one of the first businesses showing for it, which is good to see.',
            $position <= 10 => 'You’re already showing pretty well for it, but there may still be room to improve.',
            $position <= 20 => 'You’re showing up for it, but a fair way below the top results.',
            $position <= 40 => 'I noticed Google has you quite a way down the results for it, which surprised me a bit.',
            default => 'I noticed you’re appearing quite a long way down Google’s results for it, which surprised me a bit.',
        };
    }
}
