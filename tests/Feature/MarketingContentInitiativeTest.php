<?php

it('positions the home page around ongoing website management and a free website audit', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('Website management for UK small businesses')
        ->assertSee('Ongoing website management and SEO for UK small businesses')
        ->assertSee('Start your free website audit')
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'website-management-services').'"', false)
        ->assertSee('<meta name="description" content="Ongoing website management and SEO for UK small businesses, with dependable website care, lead handling, and a free website audit to show what needs attention.">', false);
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
