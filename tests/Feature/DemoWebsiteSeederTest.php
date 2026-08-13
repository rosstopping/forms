<?php

use App\Models\BusinessProfileReview;
use App\Models\ContentGeneration;
use App\Models\FormSubmission;
use App\Models\RemediationRun;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteDomain;
use Database\Seeders\DemoWebsiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('it creates a complete demo website walkthrough', function () {
    $this->seed(DemoWebsiteSeeder::class);

    $website = Website::query()
        ->whereHas('domains', fn ($query) => $query->where('domain', DemoWebsiteSeeder::DOMAIN))
        ->firstOrFail();

    expect($website->name)->toBe('Willow & Stone Garden Rooms')
        ->and($website->owner->email)->toBe(DemoWebsiteSeeder::OWNER_EMAIL)
        ->and($website->members)->toHaveCount(2)
        ->and($website->forms)->toHaveCount(3)
        ->and($website->submissions)->toHaveCount(8)
        ->and($website->submissions()->where('is_spam', true)->count())->toBe(1)
        ->and($website->healthReports)->toHaveCount(2)
        ->and($website->latestHealthReport->pages)->toHaveCount(5)
        ->and($website->repository)->not->toBeNull()
        ->and($website->searchConsoleConnection)->not->toBeNull()
        ->and($website->searchOpportunities)->toHaveCount(4)
        ->and($website->contentPlan?->enabled)->toBeTrue()
        ->and($website->contentRequests)->toHaveCount(2)
        ->and($website->businessProfileConnection)->not->toBeNull()
        ->and($website->businessProfileConnection?->reviews)->toHaveCount(3);

    expect(FormSubmission::query()->whereBelongsTo($website)->whereNotNull('follow_up_at')->count())->toBe(4)
        ->and(RemediationRun::query()->count())->toBe(2)
        ->and(ContentGeneration::query()->count())->toBe(1)
        ->and(BusinessProfileReview::query()->where('reply_status', BusinessProfileReview::STATUS_PENDING_APPROVAL)->count())->toBe(2);

    Http::fake(['*' => Http::response(['rows' => []])]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->get(route('admin.websites.show', $website))
        ->assertOk()
        ->assertSee('Willow & Stone Garden Rooms')
        ->assertSee('luxury garden office surrey');

    $this->get(route('admin.website-health-reports.show', [$website, $website->latestHealthReport]))
        ->assertOk()
        ->assertSee('Page speed and Core Web Vitals')
        ->assertSee('Structured data and schema recommendations');

    $this->get(route('admin.form-submissions.index'))
        ->assertOk()
        ->assertSee('Olivia Harper');
});

test('it can safely refresh the demo without creating duplicates', function () {
    $realUser = User::factory()->create();
    $realWebsite = Website::factory()->for($realUser, 'owner')->create();

    $this->seed(DemoWebsiteSeeder::class);
    $this->seed(DemoWebsiteSeeder::class);

    expect(WebsiteDomain::query()->where('domain', DemoWebsiteSeeder::DOMAIN)->count())->toBe(1)
        ->and(User::query()->where('email', DemoWebsiteSeeder::OWNER_EMAIL)->count())->toBe(1)
        ->and(Website::query()->whereKey($realWebsite->id)->exists())->toBeTrue()
        ->and(Website::query()->count())->toBe(2);
});
