<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Str;
use Throwable;

class MarketingAuditJourneyReview
{
    /** @return array<string, mixed> */
    public function inspect(string $html, string $url, string $contentType = 'text/html'): array
    {
        $sample = substr($html, 0, 2_000_000);
        $isHtml = in_array(strtolower(trim(explode(';', $contentType)[0])), ['text/html', 'application/xhtml+xml'], true);
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $isHtml && $sample !== '' && $document->loadHTML('<?xml encoding="UTF-8">'.$sample, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $review = [
            'version' => 1,
            'status' => $loaded ? 'completed' : 'unavailable',
            'unavailable_reason' => $loaded ? null : 'The response could not be inspected as HTML. No visitor journey findings were generated.',
            'source_url' => $this->safeDestination($url, $url),
            'captured_at' => now()->toIso8601String(),
            'method' => 'Returned HTML',
            'sample_sha256' => hash('sha256', $sample),
            'partial' => strlen($html) > strlen($sample),
            'limitations' => 'One page of returned HTML. Scripts were not run. Mobile layout, link destinations, form validation and booking completion have not been tested. These observations do not change the website health score.',
            'findings' => [],
            'routes' => [],
        ];
        if (! $loaded) {
            return $review;
        }

        $xpath = new DOMXPath($document);
        $viewport = trim($xpath->evaluate('string(//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="viewport"]/@content)'));
        $headings = [];
        foreach ($xpath->query('//h1[not(ancestor::template)]') as $heading) {
            if (count($headings) < 5) {
                $headings[] = $this->text($heading->textContent);
            }
        }
        $headingCount = $xpath->query('//h1[not(ancestor::template)]')->length;
        $routes = $this->routes($xpath, $url);
        $forms = $this->forms($xpath);
        $unlabelled = array_sum(array_column($forms, 'unlabelled'));
        $missingAlt = $xpath->query('//img[not(@alt) and not(ancestor::template)]')->length;

        $review['routes'] = $routes;
        $review['findings'] = [
            $this->finding('mobile_viewport', 'Mobile usability', $viewport === '' ? 'Mobile viewport declaration not found' : 'Mobile viewport declaration found',
                $viewport === '' ? 'No viewport meta element was found in the returned HTML.' : 'Viewport content: '.$this->text($viewport),
                'Check the page at a mobile viewport for readable text, horizontal scrolling and usable navigation.',
                '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="viewport"]/@content', $viewport ?: 'No matching element', $viewport === ''),
            $this->finding('primary_heading', 'Content', 'Main heading count: '.$headingCount,
                $headings === [] ? 'No H1 element was found in the returned HTML.' : 'H1 text: '.implode(' | ', $headings),
                'Review whether the opening content explains the service and the next step for a visitor.',
                '//h1[not(ancestor::template)]', implode(' | ', $headings) ?: 'No matching element', $headingCount !== 1),
            $this->finding('enquiry_routes', 'Enquiry routes', $routes === [] ? 'No matching enquiry or booking links found' : count($routes).' potential enquiry or booking links found',
                $routes === [] ? 'No links matched contact, enquiry, booking, telephone or email patterns. Script-rendered routes may still exist.' : 'A bounded sample of matching links is listed below. Destinations were not opened.',
                'Follow the relevant route in a browser and check that its label and next step match the visitor’s intent.',
                '//a[@href and not(ancestor::template)]', $routes === [] ? 'No matching links' : implode(' | ', array_column($routes, 'label')), $routes === []),
            $this->finding('form_requirements', 'Booking and forms', 'Form elements inspected: '.count($forms),
                $forms === [] ? 'No form element was found in the returned HTML. Forms may be on another page or rendered by scripts.' : implode(' ', array_map(fn (array $form): string => 'Form '.$form['number'].': '.$form['fields'].' input controls, '.$form['required'].' marked required and '.$form['unlabelled'].' without a detected label.', $forms)),
                'Check the enquiry or booking journey for required fields, clear prices, validation and the explanation after submission. No form was submitted.',
                '(//form[not(ancestor::template)])[position()<=6]', $forms === [] ? 'No matching element' : json_encode($forms, JSON_UNESCAPED_UNICODE), $unlabelled > 0),
        ];
        if ($missingAlt > 0) {
            $review['findings'][] = $this->finding('image_descriptions', 'Content', 'Image elements without an alt attribute: '.$missingAlt,
                'Images without an alt attribute: '.$missingAlt.'. Decorative intent has not been assessed.',
                'Review the images and add suitable descriptions or an empty alt attribute for decorative images.',
                '//img[not(@alt) and not(ancestor::template)]', $missingAlt.' matching elements', true);
        }
        if (preg_match('/(?:user-scalable\s*=\s*(?:no|0)\b|maximum-scale\s*=\s*(?:0(?:\.\d+)?|1(?:\.0+)?)\s*(?:,|;|$))/i', $viewport)) {
            $review['findings'][] = $this->finding('mobile_zoom', 'Mobile usability', 'Viewport settings may restrict zoom',
                'The viewport declaration includes a setting that can restrict zoom in some browsers.',
                'Remove unnecessary zoom restrictions and check zoom behaviour on supported devices.',
                '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="viewport"]/@content', $this->text($viewport), true);
        }

        return $review;
    }

    /** @return array<int, array{label: string, destination: string, type: string}> */
    private function routes(DOMXPath $xpath, string $url): array
    {
        $routes = [];
        foreach ($xpath->query('//a[@href and not(ancestor::template)]') as $node) {
            $href = trim($node->getAttribute('href'));
            $label = $this->text($node->getAttribute('aria-label') ?: $node->textContent);
            if (! preg_match('/\b(?:contact|enquir\w*|book\w*|reserv\w*|availability)\b|mailto:|tel:|wa\.me|whatsapp/i', $label.' '.$href)) {
                continue;
            }
            $destination = $this->safeDestination($href, $url);
            if ($destination === null) {
                continue;
            }
            $key = $destination.'|'.$label;
            $routes[$key] = ['label' => $label ?: 'Link without text', 'destination' => $destination, 'type' => str_starts_with($destination, 'mailto:') ? 'Email' : (str_starts_with($destination, 'tel:') ? 'Telephone' : 'Website link')];
            if (count($routes) >= 8) {
                break;
            }
        }

        return array_values($routes);
    }

    private function safeDestination(string $value, string $base): ?string
    {
        if (preg_match('/[\x00-\x20\x7f]/', $value) || strlen($value) > 2048) {
            return null;
        }
        if (preg_match('/^(mailto|tel):/i', $value)) {
            return Str::limit(preg_replace('/[?#].*$/', '', $value), 200, '');
        }
        if ($value === '' || str_starts_with($value, '#')) {
            return null;
        }
        try {
            $uri = UriResolver::resolve(new Uri($base), new Uri($value));
            if (! in_array($uri->getScheme(), ['http', 'https'], true) || $uri->getHost() === '' || $uri->getUserInfo() !== '') {
                return null;
            }

            return (string) $uri->withQuery('')->withFragment('');
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<int, array{number: int, fields: int, required: int, unlabelled: int}> */
    private function forms(DOMXPath $xpath): array
    {
        $forms = [];
        $labelledIds = [];
        foreach ($xpath->query('//label[@for]') as $label) {
            if (trim($label->textContent) !== '') {
                $labelledIds[$label->getAttribute('for')] = true;
            }
        }
        foreach ($xpath->query('//form[not(ancestor::template)]') as $index => $form) {
            if (count($forms) >= 6) {
                break;
            }
            $summary = ['number' => $index + 1, 'fields' => 0, 'required' => 0, 'unlabelled' => 0];
            foreach ($xpath->query('.//input|.//select|.//textarea', $form) as $control) {
                if (in_array(strtolower($control->getAttribute('type')), ['hidden', 'submit', 'reset', 'button', 'image'], true) || $control->hasAttribute('disabled') || $control->hasAttribute('hidden')) {
                    continue;
                }
                $summary['fields']++;
                $summary['required'] += (int) ($control->hasAttribute('required') || $control->getAttribute('aria-required') === 'true');
                $summary['unlabelled'] += (int) ! $this->hasLabel($control, $xpath, $labelledIds);
            }
            $forms[] = $summary;
        }

        return $forms;
    }

    /** @param array<string, bool> $labelledIds */
    private function hasLabel(DOMElement $control, DOMXPath $xpath, array $labelledIds): bool
    {
        if (trim($control->getAttribute('aria-label')) !== '' || trim($control->getAttribute('title')) !== '') {
            return true;
        }
        foreach (preg_split('/\s+/', trim($control->getAttribute('aria-labelledby'))) ?: [] as $id) {
            $label = $control->ownerDocument->getElementById($id);
            if ($label !== null && trim($label->textContent) !== '') {
                return true;
            }
        }
        foreach ($xpath->query('ancestor::label', $control) as $label) {
            if (trim($label->textContent) !== '') {
                return true;
            }
        }

        return $control->getAttribute('id') !== '' && isset($labelledIds[$control->getAttribute('id')]);
    }

    private function text(string $text): string
    {
        return Str::limit(Str::squish($text), 240, '…');
    }

    /** @return array<string, mixed> */
    private function finding(string $key, string $category, string $title, string $observation, string $recommendation, string $selector, string $excerpt, bool $needsReview): array
    {
        return [
            'key' => $key,
            'category' => $category,
            'title' => $title,
            'kind' => 'HTML observation',
            'status' => $needsReview ? 'Review suggested' : 'Observed',
            'observation' => $observation,
            'recommendation' => $recommendation,
            'evidence' => ['selector' => $selector, 'excerpt' => $excerpt],
        ];
    }
}
