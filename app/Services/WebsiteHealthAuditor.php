<?php

namespace App\Services;

use App\Models\Website;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WebsiteHealthAuditor
{
    /**
     * @return array{overall_status: string, passed_checks: int, warning_checks: int, failed_checks: int, categories: array<string, array<string, int>>, checks: array<int, array<string, mixed>>, metrics: array<string, mixed>}
     */
    public function audit(Website $website): array
    {
        $domain = $website->primaryDomain()?->domain;

        if (! $domain) {
            throw new RuntimeException('The website does not have a registered domain.');
        }

        $this->ensurePublicDomain($domain);

        $url = 'https://'.$domain;
        $checks = [];
        $metrics = $this->formMetrics($website);

        try {
            $startedAt = microtime(true);
            $response = $this->request()->get($url);
            $metrics['response_time_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $metrics['http_status'] = $response->status();

            $checks = [
                ...$checks,
                ...$this->availabilityChecks($response, $metrics['response_time_ms']),
                ...$this->inspectHtml($this->boundedBody($response), $url),
                ...$this->securityHeaderChecks($response),
            ];
        } catch (ConnectionException $exception) {
            $checks[] = $this->check('availability', 'website_reachable', 'Website reachable', 'failed', 'The homepage could not be reached: '.$exception->getMessage());
        }

        $checks[] = $this->endpointCheck($url.'/robots.txt', 'robots_txt', 'robots.txt available');
        $checks[] = $this->endpointCheck($url.'/sitemap.xml', 'sitemap_xml', 'XML sitemap available');
        $checks = [...$checks, ...$this->formChecks($metrics)];

        return $this->summarise($checks, $metrics);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function inspectHtml(string $html, string $url): array
    {
        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $loaded) {
            return [$this->check('seo', 'html_parseable', 'HTML can be analysed', 'failed', 'The homepage did not return analysable HTML.')];
        }

        $xpath = new DOMXPath($document);
        $title = trim((string) $xpath->evaluate('string(//title[1])'));
        $description = trim((string) $xpath->evaluate('string(//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="description"]/@content)'));
        $h1Count = (int) $xpath->evaluate('count(//h1)');
        $canonical = trim((string) $xpath->evaluate('string(//link[translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="canonical"]/@href)'));
        $robots = Str::lower(trim((string) $xpath->evaluate('string(//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="robots"]/@content)')));
        $language = trim((string) $xpath->evaluate('string(/html/@lang)'));
        $viewport = trim((string) $xpath->evaluate('string(//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="viewport"]/@content)'));
        $images = (int) $xpath->evaluate('count(//img)');
        $imagesWithoutAlt = (int) $xpath->evaluate('count(//img[not(@alt) or normalize-space(@alt)=""])');
        $structuredDataNodes = $xpath->query('//script[translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="application/ld+json"]');
        $invalidStructuredData = 0;

        if ($structuredDataNodes !== false) {
            foreach ($structuredDataNodes as $node) {
                json_decode($node->textContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $invalidStructuredData++;
                }
            }
        }

        $canonicalHost = $canonical ? parse_url($canonical, PHP_URL_HOST) : null;
        $expectedHost = parse_url($url, PHP_URL_HOST);

        return [
            $this->check('seo', 'page_title', 'Page title', $title === '' ? 'failed' : (Str::length($title) > 65 ? 'warning' : 'passed'), $title === '' ? 'The homepage has no title.' : 'Title: '.$title, ['value' => $title]),
            $this->check('seo', 'meta_description', 'Meta description', $description === '' ? 'warning' : (Str::length($description) > 170 ? 'warning' : 'passed'), $description === '' ? 'The homepage has no meta description.' : 'Meta description is present.', ['value' => $description]),
            $this->check('seo', 'h1', 'Primary heading', $h1Count === 1 ? 'passed' : 'warning', $h1Count === 1 ? 'The homepage has one H1.' : "The homepage has {$h1Count} H1 elements.", ['count' => $h1Count]),
            $this->check('seo', 'canonical', 'Canonical URL', $canonical === '' || $canonicalHost !== $expectedHost ? 'warning' : 'passed', $canonical === '' ? 'No canonical URL was found.' : 'Canonical: '.$canonical, ['value' => $canonical]),
            $this->check('seo', 'indexable', 'Indexing directive', Str::contains($robots, 'noindex') ? 'failed' : 'passed', Str::contains($robots, 'noindex') ? 'The homepage contains a noindex directive.' : 'No noindex directive was found.'),
            $this->check('seo', 'language', 'Page language', $language === '' ? 'warning' : 'passed', $language === '' ? 'The HTML element has no language.' : 'Language: '.$language),
            $this->check('seo', 'viewport', 'Mobile viewport', $viewport === '' ? 'warning' : 'passed', $viewport === '' ? 'No mobile viewport was found.' : 'A mobile viewport is configured.'),
            $this->check('seo', 'image_alt_text', 'Image alternative text', $imagesWithoutAlt > 0 ? 'warning' : 'passed', $imagesWithoutAlt > 0 ? "{$imagesWithoutAlt} of {$images} images have no alternative text." : 'All images have alternative text.', ['images' => $images, 'missing' => $imagesWithoutAlt]),
            $this->check('seo', 'structured_data', 'Structured data syntax', $invalidStructuredData > 0 ? 'warning' : 'passed', $invalidStructuredData > 0 ? "{$invalidStructuredData} structured data blocks contain invalid JSON." : 'No invalid structured data JSON was found.'),
        ];
    }

    protected function request(): PendingRequest
    {
        return Http::accept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
            ->withUserAgent(config('app.name').' Website Health Monitor')
            ->connectTimeout(config('forms.health_reports.connect_timeout'))
            ->timeout(config('forms.health_reports.timeout'))
            ->withOptions(['allow_redirects' => false]);
    }

    /** @return array<int, array<string, mixed>> */
    protected function availabilityChecks(Response $response, int $responseTime): array
    {
        $status = $response->status();
        $statusResult = $status >= 200 && $status < 300 ? 'passed' : ($status >= 300 && $status < 400 ? 'warning' : 'failed');

        return [
            $this->check('availability', 'website_reachable', 'Website reachable', $statusResult, "The homepage returned HTTP {$status}.", ['status' => $status]),
            $this->check('availability', 'response_time', 'Homepage response time', $responseTime > 2000 ? 'warning' : 'passed', "The homepage responded in {$responseTime} ms.", ['milliseconds' => $responseTime]),
            $this->check('availability', 'https', 'HTTPS enabled', 'passed', 'The homepage is available over HTTPS.'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function securityHeaderChecks(Response $response): array
    {
        $headers = [
            'strict-transport-security' => ['HSTS', 'Protects future visits by requiring HTTPS.'],
            'content-security-policy' => ['Content Security Policy', 'Restricts which content the browser may execute.'],
            'x-content-type-options' => ['Content type protection', 'Prevents content-type sniffing.'],
            'referrer-policy' => ['Referrer Policy', 'Controls referrer information shared with other sites.'],
        ];
        $checks = [];

        foreach ($headers as $header => [$label, $description]) {
            $present = $response->header($header) !== null;
            $checks[] = $this->check('security', str_replace('-', '_', $header), $label, $present ? 'passed' : 'warning', $present ? "The {$label} header is present." : $description);
        }

        $frameProtection = $response->header('x-frame-options') !== null || Str::contains(Str::lower((string) $response->header('content-security-policy')), 'frame-ancestors');
        $checks[] = $this->check('security', 'frame_protection', 'Frame protection', $frameProtection ? 'passed' : 'warning', $frameProtection ? 'Frame embedding is explicitly controlled.' : 'No frame protection was found.');

        return $checks;
    }

    protected function endpointCheck(string $url, string $key, string $label): array
    {
        try {
            $response = $this->request()->get($url);
            $passed = $response->successful();

            return $this->check('discoverability', $key, $label, $passed ? 'passed' : 'warning', $passed ? "{$label}." : "{$url} returned HTTP {$response->status()}.");
        } catch (ConnectionException) {
            return $this->check('discoverability', $key, $label, 'warning', "{$url} could not be reached.");
        }
    }

    /** @return array<string, int|string|null> */
    protected function formMetrics(Website $website): array
    {
        $current = $website->submissions()->where('created_at', '>=', now()->subDays(7));

        return [
            'submissions' => (clone $current)->count(),
            'legitimate_submissions' => (clone $current)->where('is_spam', false)->count(),
            'spam_submissions' => (clone $current)->where('is_spam', true)->count(),
            'email_failures' => (clone $current)->whereNotNull('email_failed_at')->count(),
            'webhook_failures' => (clone $current)->whereNotNull('webhook_failed_at')->count(),
            'last_legitimate_submission_at' => $website->submissions()->where('is_spam', false)->latest('created_at')->value('created_at'),
        ];
    }

    /** @param array<string, mixed> $metrics
     * @return array<int, array<string, mixed>>
     */
    protected function formChecks(array $metrics): array
    {
        $deliveryFailures = $metrics['email_failures'] + $metrics['webhook_failures'];

        return [
            $this->check('forms', 'submission_activity', 'Submission activity', 'passed', "{$metrics['legitimate_submissions']} legitimate and {$metrics['spam_submissions']} spam submissions were received in the last seven days."),
            $this->check('forms', 'notification_delivery', 'Notification delivery', $deliveryFailures > 0 ? 'failed' : 'passed', $deliveryFailures > 0 ? "{$deliveryFailures} email or webhook deliveries failed." : 'No email or webhook delivery failures were recorded.'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $checks
     * @param  array<string, mixed>  $metrics
     * @return array{overall_status: string, passed_checks: int, warning_checks: int, failed_checks: int, categories: array<string, array<string, int>>, checks: array<int, array<string, mixed>>, metrics: array<string, mixed>}
     */
    protected function summarise(array $checks, array $metrics): array
    {
        $totals = ['passed' => 0, 'warning' => 0, 'failed' => 0];
        $categories = [];

        foreach ($checks as $check) {
            $totals[$check['status']]++;
            $categories[$check['category']] ??= ['passed' => 0, 'warning' => 0, 'failed' => 0];
            $categories[$check['category']][$check['status']]++;
        }

        return [
            'overall_status' => $totals['failed'] > 0 ? 'critical' : ($totals['warning'] > 0 ? 'needs_attention' : 'healthy'),
            'passed_checks' => $totals['passed'],
            'warning_checks' => $totals['warning'],
            'failed_checks' => $totals['failed'],
            'categories' => $categories,
            'checks' => $checks,
            'metrics' => $metrics,
        ];
    }

    /** @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    protected function check(string $category, string $key, string $label, string $status, string $message, array $details = []): array
    {
        return compact('category', 'key', 'label', 'status', 'message', 'details');
    }

    protected function boundedBody(Response $response): string
    {
        $maximumBytes = config('forms.health_reports.max_response_kb') * 1024;

        return substr($response->body(), 0, $maximumBytes);
    }

    protected function ensurePublicDomain(string $domain): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $records = dns_get_record($domain, DNS_A | DNS_AAAA);
        $addresses = collect($records)->map(fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null)->filter();

        if ($addresses->isEmpty()) {
            throw new RuntimeException('The registered domain does not resolve to a public address.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('Health reports cannot request private or reserved network addresses.');
            }
        }
    }
}
