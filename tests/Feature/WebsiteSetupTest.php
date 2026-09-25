<?php

use App\Models\ContentPlan;
use App\Models\GithubUserAuthorization;
use App\Models\SeoTargetKeyword;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Models\WebsiteSetup;
use Illuminate\Support\Facades\Queue;

function websiteSetupAnswers(): array
{
    return [
        'business' => ['name' => 'Acme Heating', 'services' => 'Commercial heating installations', 'audience' => 'Facilities managers', 'locations' => 'South Yorkshire', 'difference' => 'Specialists in commercial premises'],
        'goals' => ['objective' => 'More installation enquiries', 'priority_services' => 'Commercial boilers', 'avoid' => 'Domestic repairs', 'success_measure' => 'Qualified installation enquiries'],
        'connection' => ['method' => 'later'],
        'google' => ['local_business' => true],
        'targets' => ['keywords' => "commercial heating sheffield\nCommercial Heating Sheffield\ncommercial boilers doncaster", 'competitors' => "https://www.competitor.example/services\ncompetitor.example"],
        'content' => ['language' => 'British English', 'tone' => 'Direct and practical', 'facts' => 'Gas Safe registered', 'examples' => 'Clear explanations', 'avoid' => 'Guaranteed rankings', 'guidance' => 'Explain technical terms.'],
        'delivery' => ['enabled' => false, 'weekday' => 1, 'additional_weekdays' => [], 'hour' => 8, 'timezone' => 'Europe/London', 'competitor_research_mode' => 'manual', 'health_reports_enabled' => false, 'weekly_ranking_reports_enabled' => false, 'email_enabled' => true, 'email_recipients' => 'hello@acme.example, sales@acme.example'],
    ];
}

it('creates an admin client setup with paused automation and a manager', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $manager = User::factory()->create();

    $this->actingAs($admin)->get(route('admin.website-setup.create'))->assertSuccessful()->assertSee('Set up a client.');
    $this->post(route('admin.website-setup.store'), [
        ...websiteSetupAnswers()['business'], 'domain' => 'https://www.Acme.example/contact', 'manager_id' => $manager->id,
    ])->assertRedirect();

    $website = Website::query()->where('name', 'Acme Heating')->firstOrFail();
    expect($website->primaryDomain()->domain)->toBe('acme.example')
        ->and($website->user_id)->toBe($manager->id)
        ->and($website->members()->first()->pivot->role)->toBe(Website::MEMBER_ROLE_MANAGER)
        ->and($website->health_reports_enabled)->toBeFalse()
        ->and($website->seo_weekly_snapshots_enabled)->toBeFalse()
        ->and($website->email_enabled)->toBeFalse()
        ->and($website->contentPlan)->toBeNull();
    $setup = WebsiteSetup::query()->where('website_id', $website->id)->firstOrFail();
    expect($setup->saved_steps)->toBe(['business'])->and($setup->current_step)->toBe('goals');
    Queue::assertNothingPushed();
});

it('keeps every wizard endpoint restricted to administrators', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);
    $this->get(route('admin.website-setup.create'))->assertForbidden();
    $this->post(route('admin.website-setup.store'), websiteSetupAnswers()['business'])->assertForbidden();
    $this->get(route('admin.website-setup.edit', $website))->assertForbidden();
    $this->put(route('admin.website-setup.update', [$website, 'business']), websiteSetupAnswers()['business'])->assertForbidden();
    $this->put(route('admin.website-setup.update', [$website, 'review']), ['confirm' => true])->assertForbidden();
    $this->post(route('admin.website-setup.pairing', $website))->assertForbidden();
    expect(WebsiteSetup::count())->toBe(0);
});

it('renders every setup step without starting jobs or creating draft records', function (string $step): void {
    Queue::fake();
    $website = Website::factory()->create();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->get(route('admin.website-setup.edit', [$website, $step]))
        ->assertSuccessful()->assertSee(WebsiteSetup::STEPS[$step]);
    expect(WebsiteSetup::count())->toBe(0);
    Queue::assertNothingPushed();
})->with(array_keys(WebsiteSetup::STEPS));

it('saves and resumes a draft without changing live settings', function (): void {
    $website = Website::factory()->create(['name' => 'Original name']);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)->put(route('admin.website-setup.update', [$website, 'business']), [
        ...websiteSetupAnswers()['business'], 'navigation' => 'save',
    ])->assertRedirect(route('admin.websites.index'));

    expect($website->fresh()->name)->toBe('Original name');
    $this->get(route('admin.website-setup.edit', $website))->assertRedirect(route('admin.website-setup.edit', [$website, 'business']));
    $this->get(route('admin.website-setup.edit', [$website, 'business']))->assertSee('Acme Heating')->assertSee('Facilities managers');
});

it('applies a complete wizard to the existing feature settings exactly once', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create(['seo_weekly_snapshots_enabled' => false]);
    $this->actingAs($admin);
    foreach (websiteSetupAnswers() as $step => $answers) {
        $this->put(route('admin.website-setup.update', [$website, $step]), $answers)->assertSessionHasNoErrors()->assertRedirect();
    }
    expect($website->contentPlan()->exists())->toBeFalse()->and($website->seoTargetKeywords()->count())->toBe(0);
    $this->put(route('admin.website-setup.update', [$website, 'review']), ['confirm' => true])->assertSessionHasNoErrors()->assertRedirect();
    $website->refresh();
    expect($website->name)->toBe('Acme Heating')
        ->and($website->email_recipients)->toBe(['hello@acme.example', 'sales@acme.example'])
        ->and($website->seoTargetKeywords()->count())->toBe(2)
        ->and($website->competitors()->count())->toBe(1)
        ->and($website->competitors()->first()->domain)->toBe('competitor.example')
        ->and($website->contentPlan->audience)->toBe('Facilities managers')
        ->and($website->contentPlan->guidance)->toContain('British English', 'Commercial boilers', 'Gas Safe registered', 'Guaranteed rankings', 'Explain technical terms.')
        ->and($website->contentPlan->enabled)->toBeFalse()
        ->and($website->seo_weekly_snapshots_enabled)->toBeFalse()
        ->and(WebsiteSetup::query()->where('website_id', $website->id)->first()->completed_at)->not->toBeNull();
    $this->put(route('admin.website-setup.update', [$website, 'review']), ['confirm' => true])->assertSessionHasNoErrors();
    expect($website->contentPlan()->count())->toBe(1)->and($website->seoTargetKeywords()->count())->toBe(2);
    Queue::assertNothingPushed();
});

it('rejects finishing incomplete setup', function (): void {
    $website = Website::factory()->create();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->put(route('admin.website-setup.update', [$website, 'review']), ['confirm' => true])
        ->assertSessionHasErrors('confirm');
    expect($website->contentPlan()->exists())->toBeFalse();
});

it('rejects invalid targets and never saves a failed step', function (array $data, string $error): void {
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'acme.example', 'is_primary' => true]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->put(route('admin.website-setup.update', [$website, 'targets']), $data)->assertSessionHasErrors($error);
    expect(WebsiteSetup::count())->toBe(0);
})->with([
    'too many keywords' => [['keywords' => implode("\n", range(1, 21))], 'keywords'],
    'overlong term' => [['keywords' => str_repeat('x', 256)], 'keywords'],
    'own domain' => [['competitors' => 'www.acme.example'], 'competitors'],
    'private IP' => [['competitors' => '127.0.0.1'], 'competitors'],
    'credentials in URL' => [['competitors' => 'https://secret:password@example.com'], 'competitors'],
]);

it('validates schedule and notification inputs', function (array $overrides, string $error): void {
    $website = Website::factory()->create();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->put(route('admin.website-setup.update', [$website, 'delivery']), [...websiteSetupAnswers()['delivery'], ...$overrides])
        ->assertSessionHasErrors($error);
})->with([
    'timezone' => [['timezone' => 'Invalid/Timezone'], 'timezone'],
    'recipients' => [['email_recipients' => 'not-an-email'], 'email_recipients'],
    'required recipients' => [['email_recipients' => ''], 'email_recipients'],
    'duplicate days' => [['additional_weekdays' => [1]], 'additional_weekdays.0'],
    'invalid research mode' => [['competitor_research_mode' => 'publish'], 'competitor_research_mode'],
]);

it('rejects duplicate website domains without creating an orphan setup', function (): void {
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'acme.example', 'is_primary' => true]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->post(route('admin.website-setup.store'), [...websiteSetupAnswers()['business'], 'domain' => 'https://www.acme.example'])
        ->assertSessionHasErrors('domain');
    expect(WebsiteSetup::count())->toBe(0)->and(Website::count())->toBe(1);
});

it('blocks activation when scheduled content is not ready and rolls back all changes', function (): void {
    $website = Website::factory()->create(['name' => 'Original name']);
    $answers = websiteSetupAnswers();
    $answers['delivery']['enabled'] = true;
    $setup = WebsiteSetup::factory()->create(['website_id' => $website->id, 'data' => $answers, 'saved_steps' => array_keys($answers)]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->put(route('admin.website-setup.update', [$website, 'review']), ['confirm' => true])->assertSessionHasErrors('confirm');
    expect($website->fresh()->name)->toBe('Original name')
        ->and($website->seoTargetKeywords()->count())->toBe(0)
        ->and($website->contentPlan()->exists())->toBeFalse()
        ->and($setup->fresh()->completed_at)->toBeNull();
});

it('enforces the total active keyword limit at activation', function (): void {
    $website = Website::factory()->create();
    SeoTargetKeyword::factory()->count(19)->create(['website_id' => $website->id]);
    $answers = websiteSetupAnswers();
    WebsiteSetup::factory()->create(['website_id' => $website->id, 'data' => $answers, 'saved_steps' => array_keys($answers)]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->put(route('admin.website-setup.update', [$website, 'review']), ['confirm' => true])->assertSessionHasErrors('confirm');
    expect($website->seoTargetKeywords()->count())->toBe(19)->and($website->contentPlan()->exists())->toBeFalse();
});

it('preserves existing targets priorities archived terms and unrelated settings', function (): void {
    $website = Website::factory()->create(['seo_weekly_snapshots_enabled' => true, 'webhook_enabled' => true]);
    $active = SeoTargetKeyword::factory()->create(['website_id' => $website->id, 'term' => 'commercial heating sheffield', 'priority' => 'high']);
    $archived = SeoTargetKeyword::factory()->create(['website_id' => $website->id, 'term' => 'commercial boilers doncaster', 'archived_at' => now()]);
    $answers = websiteSetupAnswers();
    WebsiteSetup::factory()->create(['website_id' => $website->id, 'data' => $answers, 'saved_steps' => array_keys($answers)]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->put(route('admin.website-setup.update', [$website, 'review']), ['confirm' => true])->assertSessionHasNoErrors();
    expect($active->fresh()->priority)->toBe('high')->and($archived->fresh()->archived_at)->not->toBeNull()
        ->and($website->fresh()->seo_weekly_snapshots_enabled)->toBeTrue()->and($website->fresh()->webhook_enabled)->toBeTrue();
});

it('prefills existing guidance and escapes draft text', function (): void {
    $website = Website::factory()->create();
    ContentPlan::factory()->create(['website_id' => $website->id, 'guidance' => 'Keep our existing voice.']);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->get(route('admin.website-setup.edit', [$website, 'content']))->assertSee('Keep our existing voice.');
    $this->put(route('admin.website-setup.update', [$website, 'content']), [...websiteSetupAnswers()['content'], 'guidance' => '<script>alert(1)</script>']);
    $this->get(route('admin.website-setup.edit', [$website, 'content']))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});

it('requires a repository before issuing a WordPress pairing code', function (): void {
    $website = Website::factory()->create();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->post(route('admin.website-setup.pairing', $website))->assertSessionHas('error');
    expect($website->wordpressConnection()->exists())->toBeFalse();
});

it('rejects unknown wizard steps', function (): void {
    $website = Website::factory()->create();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->get(route('admin.website-setup.edit', [$website, 'unknown']))->assertNotFound();
    $this->put(route('admin.website-setup.update', [$website, 'unknown']), [])->assertNotFound();
});

it('enables a ready content schedule and reports after explicit final confirmation', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->create(['user_id' => $admin->id]);
    $owner = User::factory()->create(['membership_tier' => 'complete']);
    $website = Website::factory()->create(['user_id' => $owner->id]);
    WebsiteRepository::factory()->create(['website_id' => $website->id]);
    $answers = websiteSetupAnswers();
    $answers['delivery'] = [...$answers['delivery'], 'enabled' => true, 'additional_weekdays' => [3, 5], 'health_reports_enabled' => true, 'weekly_ranking_reports_enabled' => true];
    WebsiteSetup::factory()->create(['website_id' => $website->id, 'data' => $answers, 'saved_steps' => array_keys($answers)]);
    $this->actingAs($admin)->put(route('admin.website-setup.update', [$website, 'review']), ['confirm' => true])->assertSessionHasNoErrors();
    expect($website->fresh()->contentPlan->enabled)->toBeTrue()
        ->and($website->fresh()->contentPlan->additional_weekdays)->toBe([3, 5])
        ->and($website->fresh()->health_reports_enabled)->toBeTrue()
        ->and($website->fresh()->weekly_ranking_reports_enabled)->toBeTrue();
    Queue::assertNothingPushed();
});

it('does not let admin setup bypass the client subscription for paid research', function (): void {
    $owner = User::factory()->create(['membership_tier' => 'essential']);
    $website = Website::factory()->create(['user_id' => $owner->id]);
    $answers = websiteSetupAnswers();
    $answers['delivery']['competitor_research_mode'] = 'research';
    WebsiteSetup::factory()->create(['website_id' => $website->id, 'data' => $answers, 'saved_steps' => array_keys($answers)]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->put(route('admin.website-setup.update', [$website, 'review']), ['confirm' => true])->assertSessionHasErrors('confirm');
    expect($website->contentPlan()->exists())->toBeFalse();
});

it('enforces the Growth content cadence at final activation', function (): void {
    $owner = User::factory()->create(['membership_tier' => 'growth']);
    $website = Website::factory()->create(['user_id' => $owner->id]);
    $answers = websiteSetupAnswers();
    $answers['delivery'] = [...$answers['delivery'], 'enabled' => true, 'additional_weekdays' => [3, 5]];
    WebsiteSetup::factory()->create(['website_id' => $website->id, 'data' => $answers, 'saved_steps' => array_keys($answers)]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->put(route('admin.website-setup.update', [$website, 'review']), ['confirm' => true])->assertSessionHasErrors('confirm');
    expect($website->contentPlan()->exists())->toBeFalse();
});

it('clears previously selected additional days when saving the schedule', function (): void {
    $website = Website::factory()->create();
    ContentPlan::factory()->create(['website_id' => $website->id, 'additional_weekdays' => [3, 5]]);
    $answers = websiteSetupAnswers()['delivery'];
    unset($answers['additional_weekdays']);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->put(route('admin.website-setup.update', [$website, 'delivery']), $answers)->assertSessionHasNoErrors();
    $this->get(route('admin.website-setup.edit', [$website, 'delivery']))->assertViewHas('values', fn (array $values): bool => $values['delivery']['additional_weekdays'] === []);
});

it('pairs WordPress inside the wizard without deploying a website', function (): void {
    Queue::fake();
    $website = Website::factory()->create();
    WebsiteRepository::factory()->create(['website_id' => $website->id]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->post(route('admin.website-setup.pairing', $website))
        ->assertRedirect(route('admin.website-setup.edit', [$website, 'connection']))
        ->assertSessionHas('wordpress_pairing_code');
    expect($website->fresh()->wordpress_enabled)->toBeTrue()
        ->and($website->fresh()->wordpressConnection->isConnected())->toBeFalse()
        ->and($website->wordpressStaticReleases()->count())->toBe(0);
    $this->get(route('admin.website-setup.edit', [$website, 'connection']))->assertSee('Waiting for WordPress to pair');
    Queue::assertNothingPushed();
});

it('keeps drafts isolated between websites', function (): void {
    $first = Website::factory()->create();
    $second = Website::factory()->create();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->put(route('admin.website-setup.update', [$first, 'goals']), websiteSetupAnswers()['goals']);
    $this->get(route('admin.website-setup.edit', [$second, 'goals']))->assertDontSee('More installation enquiries');
    expect(WebsiteSetup::query()->where('website_id', $second->id)->exists())->toBeFalse();
});

it('keeps long briefs from silently losing editorial guidance during generation', function (): void {
    $website = Website::factory()->create();
    $answers = websiteSetupAnswers();
    $answers['content']['guidance'] = str_repeat('Detailed guidance. ', 300);
    WebsiteSetup::factory()->create(['website_id' => $website->id, 'data' => $answers, 'saved_steps' => array_keys($answers)]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->put(route('admin.website-setup.update', [$website, 'review']), ['confirm' => true])->assertSessionHasErrors('confirm');
    expect($website->contentPlan()->exists())->toBeFalse();
});

it('requires an active repository installation before starting scheduled content', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->create(['user_id' => $admin->id]);
    $website = Website::factory()->create();
    $repository = WebsiteRepository::factory()->create(['website_id' => $website->id]);
    $repository->installation->update(['status' => 'suspended']);
    $answers = websiteSetupAnswers();
    $answers['delivery']['enabled'] = true;
    WebsiteSetup::factory()->create(['website_id' => $website->id, 'data' => $answers, 'saved_steps' => array_keys($answers)]);
    $this->actingAs($admin)->put(route('admin.website-setup.update', [$website, 'review']), ['confirm' => true])->assertSessionHasErrors('confirm');
    expect($website->contentPlan()->exists())->toBeFalse();
});
