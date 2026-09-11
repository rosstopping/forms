<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CompetitorPageFetcher
{
    public function __construct(private WebsiteCrawler $crawler) {}

    /** @return array<string, mixed> */
    public function fetch(string $url, string $domain): array
    {
        $originalUrl = $url;
        $seen = [];
        for ($redirect = 0; $redirect <= 5; $redirect++) {
            $host = $this->validateUrl($url, $domain);
            if (isset($seen[$url])) {
                throw new RuntimeException('Redirect loop.');
            }
            $seen[$url] = true;
            $origin = parse_url($url, PHP_URL_SCHEME).'://'.$host;
            $robots = $this->request($origin.'/robots.txt', $domain);
            if ($robots->status() !== 404 && (! $robots->successful() || ! $this->robotsAllow($this->body($robots, 128000), $url))) {
                throw new RuntimeException('Crawling is restricted or robots.txt is unavailable.');
            }
            $response = $this->request($url, $domain);
            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $location = $response->header('Location');
                if (! $location) {
                    throw new RuntimeException('Redirect has no destination.');
                }
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));

                continue;
            }
            if (! $response->successful() || ! Str::contains(strtolower($response->header('Content-Type') ?? ''), ['text/html', 'application/xhtml+xml'])) {
                throw new RuntimeException('The page did not return HTML.');
            }
            $html = $this->body($response, 1048576);
            $analysis = $this->crawler->analyseHtml($html, $url);
            unset($analysis['discovered_links']);
            $document = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new DOMXPath($document);
            $types = [];
            foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
                $data = json_decode($script->textContent, true);
                if (is_array($data)) {
                    array_walk_recursive($data, function (mixed $value, string|int $key) use (&$types): void {
                        if ($key === '@type' && is_string($value)) {
                            $types[] = Str::limit($value, 100, '');
                        }
                    });
                }
            }
            foreach ($xpath->query('//script|//style|//nav|//footer|//header|//noscript') as $node) {
                $node->parentNode?->removeChild($node);
            }
            $headings = [];
            foreach ($xpath->query('//h1|//h2|//h3') as $heading) {
                $headings[] = ['level' => $heading->nodeName, 'text' => Str::limit(trim($heading->textContent), 250, '')];
            }
            $main = $xpath->query('//main|//article')->item(0) ?? $xpath->query('//body')->item(0);
            $content = preg_replace('/\s+/u', ' ', $main?->textContent ?? '') ?? '';

            return [...$analysis, 'requested_url' => $originalUrl, 'final_url' => $url, 'headings' => array_slice($headings, 0, 40), 'main_content' => Str::limit(trim($content), 12000, ''), 'structured_data_types' => array_values(array_unique($types))];
        }
        throw new RuntimeException('Too many redirects.');
    }

    protected function validateUrl(string $url, string $domain): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || ! in_array($host, [$domain, 'www.'.$domain], true)) {
            throw new RuntimeException('The URL is outside the competitor domain.');
        }

        return $host;
    }

    /** @return array<int, string> */
    protected function addresses(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = array_values(array_filter(array_map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records ?: [])));
        if ($addresses === []) {
            throw new RuntimeException('Domain does not resolve.');
        }
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Private or reserved addresses are prohibited.');
            }
        }

        return $addresses;
    }

    private function request(string $url, string $domain): Response
    {
        $host = $this->validateUrl($url, $domain);
        $addresses = $this->addresses($host);
        $address = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];
        $port = parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80;

        return Http::withUserAgent('SitewellCompetitorBot')->accept('text/html,text/plain')->connectTimeout(3)->timeout(8)
            ->withOptions(['allow_redirects' => false, 'stream' => true, 'proxy' => '', 'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$address]]])->get($url);
    }

    private function body(Response $response, int $limit): string
    {
        if ((int) $response->header('Content-Length') > $limit) {
            throw new RuntimeException('Response is too large.');
        }
        $stream = $response->toPsrResponse()->getBody();
        $body = '';
        while (! $stream->eof() && strlen($body) <= $limit) {
            $body .= $stream->read(min(8192, $limit + 1 - strlen($body)));
        }
        $stream->close();
        if (strlen($body) > $limit) {
            throw new RuntimeException('Response is too large.');
        }

        return $body;
    }

    private function robotsAllow(string $robots, string $url): bool
    {
        $groups = [];
        $agents = [];
        $rules = [];
        foreach (explode("\n", $robots."\nUser-agent: end") as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if (! str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $name = strtolower($name);
            if ($name === 'user-agent') {
                if ($rules !== []) {
                    $groups[] = [$agents, $rules];
                    $agents = [];
                    $rules = [];
                }
                $agents[] = strtolower($value);
            } elseif (in_array($name, ['allow', 'disallow'], true) && $agents !== [] && $value !== '') {
                $rules[] = [$name, $value];
            }
        }
        $specific = collect($groups)->contains(fn (array $group): bool => in_array('sitewellcompetitorbot', $group[0], true));
        $path = (parse_url($url, PHP_URL_PATH) ?: '/').(parse_url($url, PHP_URL_QUERY) ? '?'.parse_url($url, PHP_URL_QUERY) : '');
        $matched = [];
        foreach ($groups as [$agents, $rules]) {
            if (! in_array($specific ? 'sitewellcompetitorbot' : '*', $agents, true)) {
                continue;
            }
            foreach ($rules as [$name, $pattern]) {
                $regex = str_replace(['\\*', '\\$'], ['.*', '$'], preg_quote($pattern, '~'));
                if (preg_match('~^'.$regex.'~', $path)) {
                    $matched[] = ['length' => strlen($pattern), 'allow' => $name === 'allow'];
                }
            }
        }
        usort($matched, fn (array $a, array $b): int => [$b['length'], $b['allow']] <=> [$a['length'], $a['allow']]);

        return $matched[0]['allow'] ?? true;
    }
}
