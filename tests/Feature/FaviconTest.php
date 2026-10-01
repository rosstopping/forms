<?php

use App\Models\User;

it('shows the Sitewell favicon on marketing, PPC, sign-in and workspace pages', function (): void {
    foreach ([route('marketing.home'), route('marketing.ppc.doncaster'), route('login')] as $url) {
        $this->get($url)->assertSuccessful()
            ->assertSee('href="'.asset('favicon.svg').'" type="image/svg+xml"', false)
            ->assertSee('href="'.asset('favicon.ico').'"', false)
            ->assertSee('href="'.asset('apple-touch-icon.png').'" sizes="180x180"', false);
    }

    $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertSuccessful()
        ->assertSee('href="'.asset('favicon.svg').'" type="image/svg+xml"', false)
        ->assertSee('href="'.asset('favicon.ico').'"', false)
        ->assertSee('href="'.asset('apple-touch-icon.png').'" sizes="180x180"', false);
});

it('has non-empty browser and touch icon files', function (): void {
    $svg = new DOMDocument;
    expect($svg->load(public_path('favicon.svg')))->toBeTrue();
    expect($svg->documentElement?->localName)->toBe('svg');
    expect(filesize(public_path('favicon.ico')))->toBeGreaterThan(1000);
    expect(getimagesize(public_path('apple-touch-icon.png'))[0])->toBe(180);
    expect(getimagesize(public_path('apple-touch-icon.png'))[1])->toBe(180);
});
