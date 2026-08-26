<?php

use App\Services\AutoresponderHtmlSanitizer;

it('keeps simple formatting and strips unsafe markup', function (): void {
    $sanitizer = new AutoresponderHtmlSanitizer;

    $html = $sanitizer->sanitize('<div onclick="alert(1)">Hello <strong>bold</strong> <em>italic</em><script>alert(1)</script><img src=x onerror=alert(1)></div>');

    expect($html)->toBe('<div>Hello <strong>bold</strong> <em>italic</em></div>')
        ->and($html)->not->toContain('onclick', 'script', 'img', 'onerror');
});

it('converts legacy plain text to HTML and rich text back to plain text', function (): void {
    $sanitizer = new AutoresponderHtmlSanitizer;

    $html = $sanitizer->sanitize("Hello Ada\n\nWe will be in touch.");

    expect($html)->toBe('<div>Hello Ada</div><div><br></div><div>We will be in touch.</div>')
        ->and($sanitizer->toPlainText($html))->toBe("Hello Ada\n\nWe will be in touch.");
});

it('treats an empty Trix document as empty content', function (): void {
    expect((new AutoresponderHtmlSanitizer)->sanitize('<div><br></div>'))->toBeNull();
});
