<?php

it('positions the home page around ongoing website management and a free website audit', function (): void {
    $metaDescription = 'Website care and managed SEO for UK small businesses. We fix problems, update pages and keep your enquiries organised.';

    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('UK website management &amp; SEO', false)
        ->assertSee('Website care and managed SEO for UK small businesses')
        ->assertSee('Get your free website audit')
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'website-management-services').'"', false)
        ->assertSee($metaDescription);
});

it('keeps the website management landing page distinct from reactive support pages', function (): void {
    $this->get(route('marketing.landing', 'website-management-services'))
        ->assertSuccessful()
        ->assertSee('Most small businesses need ongoing website management, not occasional rescue work')
        ->assertSee('What a fully managed website service should actually cover')
        ->assertSee('What is the difference between website management and website support?')
        ->assertSee('Clear monthly progress')
        ->assertSee('href="'.route('marketing.landing', 'small-business-website-support').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'managed-seo-services').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'improve-my-website').'"', false)
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false);
});
