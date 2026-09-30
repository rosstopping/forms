<?php

it('positions the home page around ongoing website management and a free website audit', function (): void {
    $metaDescription = 'Get found. Get chosen. Sitewell manages your website and SEO, with practical improvements carried out for you and clear weekly updates.';

    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('Turn more searches')
        ->assertSee('your next customer.')
        ->assertSee('Get your free search audit')
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false)
        ->assertDontSee('href="'.route('marketing.landing', 'website-management-services').'"', false)
        ->assertSee($metaDescription);
});

it('keeps the website management landing page distinct from reactive support pages', function (): void {
    $this->get(route('marketing.landing', 'website-management-services'))
        ->assertSuccessful()
        ->assertSee('Keep your website up to date.')
        ->assertSee('What we manage.')
        ->assertSee('What is the difference between website management and website support?')
        ->assertSee('Clear monthly progress')
        ->assertSee('href="'.route('marketing.landing', 'small-business-website-support').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'managed-seo-services').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'improve-my-website').'"', false)
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false);
});
