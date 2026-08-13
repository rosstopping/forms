<?php

namespace App\Services;

use App\Enums\OptimisationType;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use JsonException;

class OptimisationValueSanitizer
{
    private const ALLOWED_ELEMENTS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'code', 'div', 'em', 'figcaption', 'figure',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'li', 'ol', 'p', 'pre', 'small',
        'span', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
    ];

    private const ALLOWED_HTML_ATTRIBUTES = ['aria-label', 'href', 'title'];

    public const ALLOWED_CHANGE_ATTRIBUTES = ['alt', 'aria-label', 'href', 'title'];

    public function sanitize(
        OptimisationType $type,
        string $value,
        ?string $attribute = null,
        ?string $pageUrl = null,
    ): string {
        if (preg_match('/<\s*script\b/i', $value) === 1 || preg_match('/\son[a-z0-9_-]*\s*=/i', $value) === 1) {
            throw new InvalidArgumentException('Script elements and event-handler attributes are not allowed.');
        }

        return match ($type) {
            OptimisationType::Html,
            OptimisationType::AppendHtml,
            OptimisationType::PrependHtml => $this->sanitizeHtml($value),
            OptimisationType::JsonLd => $this->sanitizeJsonLd($value),
            OptimisationType::InternalLink => $this->sanitizeHref($value, $pageUrl, true),
            OptimisationType::Attribute => $this->sanitizeAttribute($attribute, $value, $pageUrl),
            default => $value,
        };
    }

    private function sanitizeHtml(string $html): string
    {
        if (preg_match('/<\s*(?:base|button|embed|form|iframe|input|link|math|meta|object|option|script|select|style|svg|textarea)\b/i', $html) === 1) {
            throw new InvalidArgumentException('Active, embedded, form, style, SVG, and script elements are not allowed.');
        }

        if (preg_match('/\sstyle\s*=/i', $html) === 1) {
            throw new InvalidArgumentException('Inline style attributes are not allowed.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="sitewell-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $loaded) {
            throw new InvalidArgumentException('The HTML fragment could not be parsed.');
        }

        $root = $document->getElementById('sitewell-root');

        if (! $root) {
            throw new InvalidArgumentException('The HTML fragment could not be parsed.');
        }

        $this->sanitizeChildren($root);
        $sanitized = '';

        foreach ($root->childNodes as $child) {
            $sanitized .= $document->saveHTML($child);
        }

        return $sanitized;
    }

    private function sanitizeChildren(DOMElement $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (! in_array($tag, self::ALLOWED_ELEMENTS, true)) {
                $this->sanitizeChildren($child);

                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }

                $parent->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);

                if (! in_array($name, self::ALLOWED_HTML_ATTRIBUTES, true)) {
                    $child->removeAttribute($attribute->name);

                    continue;
                }

                if ($name === 'href') {
                    $child->setAttribute('href', $this->sanitizeHref($attribute->value));
                }
            }

            $this->sanitizeChildren($child);
        }
    }

    private function sanitizeJsonLd(string $json): string
    {
        try {
            $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('JSON-LD must contain valid JSON.');
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('JSON-LD must contain an object or array.');
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function sanitizeAttribute(?string $attribute, string $value, ?string $pageUrl): string
    {
        if (! in_array($attribute, self::ALLOWED_CHANGE_ATTRIBUTES, true)) {
            throw new InvalidArgumentException('That attribute cannot be changed through Pixel.');
        }

        return $attribute === 'href' ? $this->sanitizeHref($value, $pageUrl) : $value;
    }

    private function sanitizeHref(string $href, ?string $pageUrl = null, bool $internalOnly = false): string
    {
        $href = trim($href);

        if ($href === '' || preg_match('/^(?:javascript|data|vbscript):/i', $href) === 1) {
            throw new InvalidArgumentException('The link target must use a safe HTTP or HTTPS URL.');
        }

        $baseUrl = $pageUrl ?: 'https://sitewell.invalid/';
        $resolvedUrl = filter_var($href, FILTER_VALIDATE_URL) ? $href : $this->resolveRelativeUrl($baseUrl, $href);
        $scheme = strtolower((string) parse_url($resolvedUrl, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('The link target must use a safe HTTP or HTTPS URL.');
        }

        if ($internalOnly && $pageUrl) {
            $pageHost = preg_replace('/^www\./i', '', strtolower((string) parse_url($pageUrl, PHP_URL_HOST)));
            $targetHost = preg_replace('/^www\./i', '', strtolower((string) parse_url($resolvedUrl, PHP_URL_HOST)));

            if ($pageHost !== $targetHost) {
                throw new InvalidArgumentException('Internal links must remain on the website hostname.');
            }
        }

        return $href;
    }

    private function resolveRelativeUrl(string $baseUrl, string $href): string
    {
        if (str_starts_with($href, '//')) {
            return ((string) parse_url($baseUrl, PHP_URL_SCHEME)).':'.$href;
        }

        $origin = ((string) parse_url($baseUrl, PHP_URL_SCHEME)).'://'.((string) parse_url($baseUrl, PHP_URL_HOST));

        return $origin.'/'.ltrim($href, '/');
    }
}
