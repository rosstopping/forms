<?php

it('shows the Sitewell WordPress plugin download page', function (): void {
    $this->get(route('marketing.wordpress'))
        ->assertSuccessful()
        ->assertSee('Sitewell by Digizu')
        ->assertSee('Version 1.0.0')
        ->assertSee('SHA-256')
        ->assertSee(route('marketing.wordpress.download'))
        ->assertSee('sitewell@digizu.co.uk');
});

it('downloads the current Sitewell WordPress plugin', function (): void {
    $this->get(route('marketing.wordpress.download'))
        ->assertDownload('sitewell-by-digizu.zip')
        ->assertHeader('content-type', 'application/zip')
        ->assertHeader('x-content-type-options', 'nosniff');
});
