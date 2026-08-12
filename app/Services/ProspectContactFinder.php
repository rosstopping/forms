<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ProspectContactFinder
{
    /** @return array{emails: array<int, array{value: string, source_url: string}>, phones: array<int, array{value: string, source_url: string}>, contact_page_url: ?string, contact_form_url: ?string} */
    public function find(string $homepageUrl, string $homepageHtml): array
    {
        $pages = [$homepageUrl => $homepageHtml];
        $contactPageUrls = $this->contactPageUrls($homepageUrl, $homepageHtml);

        foreach ($contactPageUrls as $contactPageUrl) {
            try {
                $response = Http::accept('text/html')->withUserAgent(config('app.name').' Prospect Research')
                    ->connectTimeout(5)->timeout(10)->withOptions(['allow_redirects' => false])->get($contactPageUrl);

                if ($response->successful()) {
                    $pages[$contactPageUrl] = Str::limit($response->body(), 1_000_000, '');
                }
            } catch (Throwable) {
                continue;
            }
        }

        $emails = [];
        $phones = [];
        $contactFormUrl = null;

        foreach ($pages as $url => $html) {
            $document = $this->document($html);
            $xpath = new DOMXPath($document);

            foreach ($xpath->query('//a[starts-with(translate(@href, "MAILTO", "mailto"), "mailto:")]') ?: [] as $link) {
                $email = Str::lower(Str::before(Str::after($link->getAttribute('href'), ':'), '?'));
                $this->addEmail($emails, $email, $url);
            }

            preg_match_all('/(?<![\w.+-])[\w.+-]+@[a-z0-9.-]+\.[a-z]{2,}(?![\w.-])/i', html_entity_decode(strip_tags($html)), $matches);
            foreach ($matches[0] ?? [] as $email) {
                $this->addEmail($emails, Str::lower($email), $url);
            }

            foreach ($xpath->query('//a[starts-with(translate(@href, "TEL", "tel"), "tel:")]') ?: [] as $link) {
                $phone = Str::squish(rawurldecode(Str::after($link->getAttribute('href'), ':')));
                if ($phone !== '') {
                    $phones[$phone] ??= ['value' => $phone, 'source_url' => $url];
                }
            }

            if ($contactFormUrl === null && ($xpath->query('//form')?->length ?? 0) > 0) {
                $contactFormUrl = $url;
            }
        }

        return [
            'emails' => array_values($emails),
            'phones' => array_values($phones),
            'contact_page_url' => $contactPageUrls[0] ?? null,
            'contact_form_url' => $contactFormUrl,
        ];
    }

    /** @return array<int, string> */
    protected function contactPageUrls(string $homepageUrl, string $html): array
    {
        $xpath = new DOMXPath($this->document($html));
        $homepageHost = Str::lower((string) parse_url($homepageUrl, PHP_URL_HOST));
        $urls = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = $link->getAttribute('href');
            $label = Str::lower($link->textContent.' '.$href);
            if (! Str::contains($label, ['contact', 'get-in-touch', 'get_in_touch', 'about'])) {
                continue;
            }

            $url = $this->resolveUrl($homepageUrl, $href);
            if ($url && Str::lower((string) parse_url($url, PHP_URL_HOST)) === $homepageHost) {
                $urls[$url] = $url;
            }
        }

        return array_slice(array_values($urls), 0, 2);
    }

    /** @param array<string, array{value: string, source_url: string}> $emails */
    protected function addEmail(array &$emails, string $email, string $sourceUrl): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[$email] ??= ['value' => $email, 'source_url' => $sourceUrl];
        }
    }

    protected function document(string $html): DOMDocument
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.Str::limit($html, 1_000_000, ''), LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);

        return $document;
    }

    protected function resolveUrl(string $baseUrl, string $href): ?string
    {
        $href = trim(html_entity_decode($href));
        if ($href === '' || Str::startsWith($href, ['#', 'mailto:', 'tel:', 'javascript:'])) {
            return null;
        }

        if (Str::startsWith($href, ['http://', 'https://'])) {
            return filter_var($href, FILTER_VALIDATE_URL) ? $href : null;
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $port = parse_url($baseUrl, PHP_URL_PORT);
        if (! is_string($scheme) || ! is_string($host)) {
            return null;
        }

        $origin = $scheme.'://'.$host.($port ? ':'.$port : '');
        if (Str::startsWith($href, '//')) {
            return $scheme.':'.$href;
        }

        if (Str::startsWith($href, '/')) {
            return $origin.$href;
        }

        $path = (string) parse_url($baseUrl, PHP_URL_PATH);
        $directory = Str::finish(Str::beforeLast($path ?: '/', '/'), '/');

        return $origin.$directory.$href;
    }
}
