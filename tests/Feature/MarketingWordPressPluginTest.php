<?php

it('shows the Sitewell WordPress plugin download page', function (): void {
    $this->get(route('marketing.wordpress'))
        ->assertSuccessful()
        ->assertSee('Sitewell by Digizu')
        ->assertSee('Version 1.0.6')
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
    foreach (['sitewell-static-frontend.php', 'src/FrontendRouter.php', 'src/Plugin.php', 'src/BypassPolicy.php', 'src/DirectDelivery.php', 'src/DeliveryRules.php', 'src/UploadsDelivery.php', 'src/PluginUpdater.php', 'readme.txt'] as $file) {
        expect($archive->getFromName('sitewell-by-digizu/'.$file))
            ->toBe(file_get_contents(base_path('wordpress-plugin/sitewell-by-digizu/'.$file)));
    }
    $archive->close();
});

it('advertises the exact downloadable plugin with version and checksum', function (): void {
    $metadata = $this->getJson(route('marketing.wordpress.update'))
        ->assertSuccessful()
        ->assertJsonPath('version', '1.0.6')
        ->assertJsonPath('requires', '6.6')
        ->assertJsonPath('requires_php', '8.2')
        ->assertJsonPath('tested', '7.1')
        ->assertJsonPath('sha256', hash_file('sha256', base_path('wordpress-plugin/sitewell-by-digizu.zip')))
        ->json();

    $this->get($metadata['package'])->assertDownload('sitewell-by-digizu.zip');
    $this->get(route('marketing.wordpress.download', ['version' => '0.0.1']))->assertStatus(409);
    $this->get(route('marketing.wordpress.download', ['version' => ['1.0.6']]))->assertStatus(409);
});
