<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use RuntimeException;

class PhpSecurityChecker
{
    /** @return array{status: string, evidence: list<string>, url: string} */
    public function check(string $url): array
    {
        try {
            $uri = (new Uri($url))->withFragment('');
            for ($redirect = 0; $redirect <= 3; $redirect++) {
                $url = (string) $uri;
                $host = $uri->getHost();
                if (! in_array($uri->getScheme(), ['http', 'https'], true) || $uri->getUserInfo() !== '' || $uri->getPort() !== null || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || filter_var($host, FILTER_VALIDATE_IP) || ! str_contains($host, '.') || preg_match('/[\\\\\x00-\x20\x7f]/', $url)) {
                    throw new RuntimeException('Unsupported address.');
                }
                $addresses = $this->addresses($host);
                if ($addresses === []) {
                    throw new RuntimeException('No public destination.');
                }
                foreach ($addresses as $address) {
                    if (! $this->isPublicAddress($address)) {
                        throw new RuntimeException('Non-public destination.');
                    }
                }
                $response = $this->request($url, $host, $addresses[0]);
                if (in_array($response['status'], [301, 302, 303, 307, 308], true)) {
                    if ($redirect === 3 || empty($response['headers']['location'])) {
                        throw new RuntimeException('Redirect limit.');
                    }
                    $uri = UriResolver::resolve($uri, new Uri($response['headers']['location']));

                    continue;
                }
                if ($response['status'] < 200 || $response['status'] >= 300 || ! str_contains(strtolower($response['headers']['content-type'] ?? ''), 'text/html')) {
                    throw new RuntimeException('Page unavailable.');
                }
                $evidence = [];
                if (preg_match('/\bPHP(?:\/|\b)/i', $response['headers']['x-powered-by'] ?? '') || preg_match('/(?:^|[,;\s])PHPSESSID=/i', $response['headers']['set-cookie'] ?? '')) {
                    $evidence[] = 'The website’s response contains a PHP indicator.';
                }
                if (preg_match('~(?:src|href)\s*=\s*["\x27][^"\x27]*?(?:/wp-content/|/wp-includes/|/wp-json/)|<meta[^>]+content\s*=\s*["\x27]WordPress\b~i', $response['body']) || str_contains($response['headers']['link'] ?? '', 'api.w.org')) {
                    $evidence[] = 'The page contains WordPress clues. WordPress normally runs on PHP, although a cached or static copy can retain these clues.';
                }

                $evidence = [...$evidence, ...$this->platformEvidence($response['headers'], $response['body'])];

                return ['status' => $evidence ? 'potential' : 'unknown', 'evidence' => $evidence, 'url' => $url];
            }
        } catch (RuntimeException|\InvalidArgumentException) {
            // Public results deliberately omit network internals and remote content.
        }

        return ['status' => 'unavailable', 'evidence' => [], 'url' => $url];
    }

    /**
     * @param  array<string, string>  $headers
     * @return list<string>
     */
    private function platformEvidence(array $headers, string $html): array
    {
        $platforms = [];
        $cookies = [];
        preg_match_all('/(?:^|,\s*)([A-Za-z0-9_-]+)=([^;,]*)/', $headers['set-cookie'] ?? '', $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $cookies[$match[1]] = $match[2];
        }
        if (isset($cookies['laravel_session']) || isset($cookies['laravel-session'])) {
            $platforms[] = 'Laravel';
        }
        if (! empty($cookies['X-Magento-Vary'])) {
            $platforms[] = 'Magento';
        }
        if (preg_match('/^Drupal(?:\s+\d|\s*\(|$)/i', $headers['x-generator'] ?? '')) {
            $platforms[] = 'Drupal';
        }
        if (preg_match('/^Craft CMS(?:[\s\/]|$)/i', $headers['x-powered-by'] ?? '')) {
            $platforms[] = 'Craft CMS';
        }
        $debugToken = $headers['x-debug-token'] ?? '';
        if (preg_match('/^[a-f0-9]{6,32}$/i', $debugToken)
            && preg_match('~/_(?:profiler)/'.preg_quote($debugToken, '~').'/?$~', parse_url($headers['x-debug-token-link'] ?? '', PHP_URL_PATH) ?: '')) {
            $platforms[] = 'Symfony';
        }
        if ($html !== '') {
            $document = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new DOMXPath($document);
                $hasCsrfMeta = false;
                foreach ($xpath->query('//meta[@name][@content]') as $meta) {
                    $name = strtolower($meta->getAttribute('name'));
                    $content = trim($meta->getAttribute('content'));
                    if ($name === 'csrf-token' && $content !== '') {
                        $hasCsrfMeta = true;
                    }
                    if ($name !== 'generator') {
                        continue;
                    }
                    foreach ([
                        'Drupal' => '/^Drupal(?:\s+\d|\s*\(|$)/i',
                        'Joomla' => '/^Joomla!?(?:\s+-\s+Open Source Content Management|\s+\d|$)/i',
                        'TYPO3' => '/^TYPO3 CMS(?:\s+\d|$)/i',
                    ] as $platform => $pattern) {
                        if (preg_match($pattern, $content)) {
                            $platforms[] = $platform;
                        }
                    }
                }
                if ($hasCsrfMeta && isset($cookies['XSRF-TOKEN'])) {
                    foreach ($cookies as $name => $value) {
                        if (preg_match('/[_-]session$/', $name) && $this->isLaravelCookie($value)) {
                            $platforms[] = 'Laravel';
                        }
                    }
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        }

        return array_map(fn (string $platform): string => $platform.' indicators were found; this does not confirm the live PHP runtime or patch status.', array_values(array_unique($platforms)));
    }

    private function isLaravelCookie(string $value): bool
    {
        $decoded = base64_decode(rawurldecode($value), true);
        if ($decoded === false) {
            return false;
        }
        $payload = json_decode($decoded, true);

        return is_array($payload)
            && is_string($payload['iv'] ?? null)
            && strlen(base64_decode($payload['iv'], true) ?: '') === 16
            && is_string($payload['value'] ?? null)
            && (base64_decode($payload['value'], true) ?: '') !== ''
            && is_string($payload['mac'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/i', $payload['mac']) === 1;
    }

    /** @return list<string> */
    protected function addresses(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        return array_values(array_filter(array_map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records ?: [])));
    }

    public function isPublicAddress(string $address): bool
    {
        if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
            return false;
        }
        if (str_contains($address, ':')) {
            $bytes = inet_pton($address);

            // Permit global unicast only; exclude transition mechanisms and special allocations.
            return $bytes !== false && (ord($bytes[0]) & 0xE0) === 0x20 && ! str_starts_with(bin2hex($bytes), '200100') && ! str_starts_with(bin2hex($bytes), '2002');
        }

        return (int) explode('.', $address)[0] < 224;
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    protected function request(string $url, string $host, string $address): array
    {
        $body = '';
        $headers = [];
        $headerBytes = 0;
        $curl = curl_init($url);
        $port = str_starts_with($url, 'https:') ? 443 : 80;
        $pinnedAddress = str_contains($address, ':') ? '['.$address.']' : $address;
        curl_setopt_array($curl, [
            CURLOPT_PROXY => '', CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 6,
            CURLOPT_RESOLVE => [$host.':'.$port.':'.$pinnedAddress],
            CURLOPT_USERAGENT => 'SitewellSecurityChecker/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Encoding: identity'],
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => function (\CurlHandle $handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 524288) {
                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => function (\CurlHandle $handle, string $line) use (&$headers, &$headerBytes): int {
                $headerBytes += strlen($line);
                if ($headerBytes > 32768) {
                    return 0;
                }
                if (str_starts_with($line, 'HTTP/')) {
                    $headers = [];
                } elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $name = strtolower(trim($name));
                    $headers[$name] = isset($headers[$name]) ? $headers[$name].', '.trim($value) : trim($value);
                }

                return strlen($line);
            },
        ]);
        try {
            if (curl_exec($curl) === false) {
                throw new RuntimeException('Request failed or exceeded its limits.');
            }

            return ['status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'headers' => $headers, 'body' => $body];
        } finally {
            curl_close($curl);
        }
    }
}
