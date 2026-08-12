<?php

use App\Services\ProspectContactFinder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('finds published contact details on the homepage and same-domain contact page', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://example.com/contact' => Http::response(<<<'HTML'
            <html><body>
                <a href="mailto:hello@example.com?subject=Hello">Email us</a>
                <a href="tel:+441171234567">0117 123 4567</a>
                <form action="/enquiry" method="post"><input name="message"></form>
            </body></html>
            HTML),
    ]);

    $contacts = app(ProspectContactFinder::class)->find('https://example.com', <<<'HTML'
        <html><body>
            <a href="/contact">Contact</a>
            <p>Alternatively email sales@example.com</p>
            <a href="https://elsewhere.example/contact">Other company</a>
        </body></html>
        HTML);

    expect($contacts['emails'])->toBe([
        ['value' => 'sales@example.com', 'source_url' => 'https://example.com'],
        ['value' => 'hello@example.com', 'source_url' => 'https://example.com/contact'],
    ])->and($contacts['phones'])->toBe([
        ['value' => '+441171234567', 'source_url' => 'https://example.com/contact'],
    ])->and($contacts['contact_page_url'])->toBe('https://example.com/contact')
        ->and($contacts['contact_form_url'])->toBe('https://example.com/contact');

    Http::assertSentCount(1);
});

it('continues without contact details when a contact page cannot be fetched', function () {
    Http::preventStrayRequests();
    Http::fake(['https://example.com/contact' => Http::response('', 403)]);

    $contacts = app(ProspectContactFinder::class)->find('https://example.com', '<a href="/contact">Contact us</a>');

    expect($contacts['emails'])->toBeEmpty()
        ->and($contacts['phones'])->toBeEmpty()
        ->and($contacts['contact_page_url'])->toBe('https://example.com/contact')
        ->and($contacts['contact_form_url'])->toBeNull();
});
