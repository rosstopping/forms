<?php

namespace App\Services;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

class AutoresponderHtmlSanitizer
{
    /** @var array<int, string> */
    private const ALLOWED_ELEMENTS = ['div', 'p', 'br', 'strong', 'em', 'ul', 'ol', 'li'];

    /** @var array<int, string> */
    private const REMOVE_WITH_CONTENTS = ['script', 'style', 'iframe', 'object', 'embed'];

    public function sanitize(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        $html = $this->convertPlainTextToHtml($html);
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div data-autoresponder-root>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root instanceof DOMElement) {
            return null;
        }

        $this->sanitizeChildren($root);

        $sanitizedHtml = collect(iterator_to_array($root->childNodes))
            ->map(fn (DOMNode $node): string => $document->saveHTML($node) ?: '')
            ->implode('');

        return blank($this->toPlainText($sanitizedHtml)) ? null : $sanitizedHtml;
    }

    public function toPlainText(string $html): string
    {
        $withLineBreaks = preg_replace('/<(br\s*\/?>|\/(?:div|p|li))>/i', "\n", $html) ?? $html;
        $plainText = html_entity_decode(strip_tags($withLineBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::of($plainText)
            ->replaceMatches("/[\t ]+\n/", "\n")
            ->replaceMatches('/\n{3,}/', "\n\n")
            ->trim()
            ->toString();
    }

    private function convertPlainTextToHtml(string $content): string
    {
        if (preg_match('/<[a-z][^>]*>/i', $content) === 1) {
            return $content;
        }

        return collect(preg_split('/\R/', $content) ?: [])
            ->map(fn (string $line): string => '<div>'.($line === '' ? '<br>' : htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</div>')
            ->implode('');
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMComment) {
                $node->parentNode?->removeChild($node);

                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            $tagName = Str::lower($node->tagName);

            if (in_array($tagName, self::REMOVE_WITH_CONTENTS, true)) {
                $node->parentNode?->removeChild($node);

                continue;
            }

            $this->sanitizeChildren($node);

            if (! in_array($tagName, self::ALLOWED_ELEMENTS, true)) {
                $this->unwrap($node);

                continue;
            }

            foreach (iterator_to_array($node->attributes) as $attribute) {
                $node->removeAttributeNode($attribute);
            }
        }
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
