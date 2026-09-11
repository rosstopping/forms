<?php

it('shows the Sitewell WordPress plugin download page', function (): void {
    $this->get(route('marketing.wordpress'))
        ->assertSuccessful()
        ->assertSee('Sitewell by Digizu')
        ->assertSee('Version 1.0.1')
        ->assertSee('SHA-256')
        ->assertSee(route('marketing.wordpress.download'))
        ->assertSee('sitewell@digizu.co.uk');
});

it('downloads the current Sitewell WordPress plugin', function (): void {
    $response = $this->get(route('marketing.wordpress.download'))
        ->assertDownload('sitewell-by-digizu.zip')
        ->assertHeader('content-type', 'application/zip')
        ->assertHeader('x-content-type-options', 'nosniff');

    $archive = new ZipArchive;
    expect($archive->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();
    foreach (['sitewell-static-frontend.php', 'src/FrontendRouter.php', 'src/Plugin.php', 'src/BypassPolicy.php', 'readme.txt'] as $file) {
        expect($archive->getFromName('sitewell-by-digizu/'.$file))
            ->toBe(file_get_contents(base_path('wordpress-plugin/sitewell-by-digizu/'.$file)));
    }
    $archive->close();
});
