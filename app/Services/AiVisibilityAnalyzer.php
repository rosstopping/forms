<?php

namespace App\Services;

use App\Models\AiVisibilitySetting;
use App\Models\Website;
use Illuminate\Support\Str;

class AiVisibilityAnalyzer
{
    /** @return array{names: array<int, string>, domains: array<int, string>} */
    public function identity(Website $website, ?AiVisibilitySetting $settings): array
    {
        $name = $settings?->brand_name ?: ($website->businessProfileConnection?->location_title ?: $website->name);
        $shortName = preg_replace('/\s+(?:limited|ltd|llc|inc)\.?$/iu', '', $name);

        return [
            'names' => collect([$name, $shortName, ...($settings?->aliases ?? [])])->filter()->unique()->values()->all(),
            'domains' => $website->domains()->pluck('domain')->map(fn (string $domain) => $this->domain($domain))->filter()->unique()->sort()->values()->all(),
        ];
    }

    public function domain(string $url): ?string
    {
        $url = trim($url);
        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }
        if (! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) || parse_url($url, PHP_URL_USER) !== null) {
            return null;
        }
        $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));

        return $host !== '' && str_contains($host, '.') ? preg_replace('/^www\./', '', $host) : null;
    }

    public function matchesDomain(string $url, string $domain): bool
    {
        $host = $this->domain($url);
        $domain = $this->domain($domain);

        return $host !== null && $domain !== null && ($host === $domain || str_ends_with($host, '.'.$domain));
    }

    /** @param array<int, string> $names */
    public function mentionsBrand(string $text, array $names): bool
    {
        $text = ' '.$this->normalize($text).' ';
        foreach ($names as $name) {
            $normalized = $this->normalize($name);
            if (mb_strlen($normalized) >= 2 && str_contains($text, ' '.$normalized.' ')) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $citations
     * @param  array{names: array<int, string>, domains: array<int, string>}  $identity
     * @return array<string, mixed>
     */
    public function analyze(string $text, array $citations, array $identity): array
    {
        $cleanCitations = collect($citations)->filter(fn ($citation) => is_string($citation['url'] ?? null) && filter_var($citation['url'], FILTER_VALIDATE_URL) && $this->domain($citation['url']) !== null)
            ->map(fn ($citation) => ['url' => $citation['url'], 'domain' => $this->domain($citation['url']), 'title' => isset($citation['title']) ? Str::limit((string) $citation['title'], 250) : null])
            ->unique('url')->take(50)->values();
        $cited = $cleanCitations->contains(fn ($citation) => collect($identity['domains'])->contains(fn ($domain) => $this->matchesDomain($citation['url'], $domain)));
        preg_match_all('~https?://[^\s<>\]\)]+~iu', $text, $urls);
        $websiteMentioned = collect($identity['domains'])->contains(function (string $domain) use ($text, $urls): bool {
            if (collect($urls[0])->contains(fn ($url) => $this->matchesDomain($url, $domain))) {
                return true;
            }

            return preg_match('/(?<![\pL\pN@.\/-])(?:[a-z0-9-]+\.)*'.preg_quote($domain, '/').'(?![\pL\pN.-])/iu', $text) === 1;
        });
        $brandText = preg_replace('~https?://[^\s<>\]\)]+|\b(?:[a-z0-9-]+\.)+[a-z]{2,}(?:/\S*)?~iu', ' ', $text);
        $mentioned = $this->mentionsBrand($brandText, $identity['names']);
        $entries = $this->recommendations($text);
        $ordered = collect($entries)->whereNotNull('position')->values();
        $validOrder = $ordered->count() >= 2 && $ordered->pluck('position')->all() === range(1, $ordered->count());
        $brandEntries = $ordered->filter(fn ($entry) => $this->mentionsBrand($entry['name'], $identity['names']));
        $position = $validOrder && $brandEntries->count() === 1 ? $brandEntries->first()['position'] : null;
        $competitors = collect($entries)->reject(fn ($entry) => $this->mentionsBrand($entry['name'], $identity['names']) || collect($identity['domains'])->contains(fn ($domain) => $this->matchesDomain($entry['name'], $domain)))
            ->map(fn ($entry) => [...$entry, 'key' => $this->normalize($entry['name']), 'position' => $validOrder ? $entry['position'] : null])
            ->unique('key')->take(20)->values()->all();

        return ['brand_mentioned' => $mentioned, 'brand_position' => $position, 'website_mentioned' => $websiteMentioned, 'website_cited' => $cited, 'citations' => $cleanCitations->all(), 'competitors' => $competitors,
            'analysis' => ['version' => 1, 'mention_without_citation' => $mentioned && ! $cited, 'position_method' => $position === null ? 'not_measurable' : 'explicit_numbered_list', 'competitor_method' => 'explicit_recommendation_labels', 'recommendations' => $entries]];
    }

    /** @return array<int, array{name: string, position: ?int}> */
    private function recommendations(string $text): array
    {
        $entries = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            if (! preg_match('/^\s*(?:(\d{1,2})[.)]\s+|[-*•]\s+|#{2,4}\s+)(?:\*\*([^*\n]+)\*\*|\[([^\]\n]+)\]\(https?:\/\/[^)]+\)|([\p{Lu}][\pL\pN &\x{2019}\x{0027}.,-]{2,100}?)(?:\s+[–—]\s|:\s|$))/u', $line, $match)) {
                continue;
            }
            $name = trim($match[2] ?: ($match[3] ?? '') ?: ($match[4] ?? ''), " \t:–—-");
            if (mb_strlen($name) < 3 || mb_strlen($name) > 120 || in_array($this->normalize($name), ['note', 'summary', 'recommendations', 'sources', 'conclusion', 'next steps', 'how to choose', 'check reviews'], true)) {
                continue;
            }
            $entries[] = ['name' => $name, 'position' => ! empty($match[1]) ? (int) $match[1] : null];
        }

        return $entries;
    }

    public function normalize(string $text): string
    {
        return trim(preg_replace('/[^\pL\pN]+/u', ' ', Str::lower(str_replace('&', ' and ', $text))));
    }
}
