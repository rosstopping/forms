<?php

use App\Ai\Agents\GoogleAdsCopyWriter;
use App\Models\GoogleAdsCampaignDraft;
use App\Models\GoogleAdsConnection;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Support\MembershipPlan;
use Laravel\Ai\Prompts\AgentPrompt;

test('AI suggests editable keywords and ad copy from the selected landing page without creating a campaign', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create(['name' => 'Acme Cycles']);
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    $report = WebsiteHealthReport::factory()->for($website)->create();
    $report->pages()->create([
        'url' => 'https://example.com/repairs',
        'url_hash' => hash('sha256', 'https://example.com/repairs'),
        'title' => 'Bicycle repairs in Doncaster',
        'meta_description' => 'Book bicycle repairs and servicing with Acme Cycles.',
    ]);
    GoogleAdsCopyWriter::fake([[
        'keywords' => ['bike repairs doncaster', 'bicycle repairs doncaster', 'bike servicing doncaster', 'cycle repair shop', 'book bike repair'],
        'headlines' => ['Bike Repairs Doncaster', 'Book Your Bike Repair', 'Acme Cycles Servicing'],
        'descriptions' => ['Book bicycle repairs with Acme Cycles in Doncaster.', 'Get your bike serviced by Acme Cycles.'],
    ]])->preventStrayPrompts();

    $response = $this->actingAs($owner)->post(route('admin.google-ads.suggestions', $website), [
        'name' => 'Bike repair search',
        'daily_budget' => 20,
        'city_name' => 'Doncaster',
        'radius_miles' => 20,
        'final_url' => 'https://example.com/repairs',
    ]);

    $response->assertRedirect()->assertSessionHas('status')
        ->assertSessionHas('_old_input.keywords_text', "bike repairs doncaster\nbicycle repairs doncaster\nbike servicing doncaster\ncycle repair shop\nbook bike repair")
        ->assertSessionHas('_old_input.headlines.0', 'Bike Repairs Doncaster')
        ->assertSessionHas('_old_input.descriptions.0', 'Book bicycle repairs with Acme Cycles in Doncaster.')
        ->assertSessionHas('_old_input.daily_budget', 20);
    GoogleAdsCopyWriter::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Bicycle repairs in Doncaster'));
    expect(GoogleAdsCampaignDraft::query()->count())->toBe(0);
});

test('AI suggestions need a verified landing page and usable business facts', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    GoogleAdsCopyWriter::fake()->preventStrayPrompts();

    $this->actingAs($owner)->post(route('admin.google-ads.suggestions', $website), [
        'final_url' => 'https://other.example.com/', 'city_name' => 'Doncaster',
    ])->assertSessionHasErrors('final_url');
    $this->actingAs($owner)->post(route('admin.google-ads.suggestions', $website), [
        'final_url' => 'https://example.com/', 'city_name' => 'Doncaster',
    ])->assertSessionHas('error', 'Add a short description of what this landing page offers, then try again.');
    GoogleAdsCopyWriter::assertNeverPrompted();
});

test('invalid AI output is not filled into the campaign form', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    GoogleAdsCopyWriter::fake([[
        'keywords' => ['bike repairs', 'bike repairs', 'cycle repairs', 'bike servicing', 'book bike repair'],
        'headlines' => ['Bike Repairs', 'Bike Repairs', 'Bike Servicing'],
        'descriptions' => ['Book a bike repair.', 'Book a bike repair.'],
    ]])->preventStrayPrompts();

    $this->actingAs($owner)->post(route('admin.google-ads.suggestions', $website), [
        'final_url' => 'https://example.com/', 'city_name' => 'Doncaster', 'campaign_brief' => 'Bicycle repairs and servicing.',
    ])->assertSessionHas('error')->assertSessionMissing('_old_input.keywords_text');
});

test('suggestions are limited to website managers on Complete', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $viewer = User::factory()->create();
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $data = ['final_url' => 'https://example.com/', 'city_name' => 'Doncaster', 'campaign_brief' => 'Bike repairs'];

    $this->actingAs($viewer)->post(route('admin.google-ads.suggestions', $website), $data)->assertForbidden();
    $owner->update(['membership_tier' => MembershipPlan::GROWTH]);
    $this->actingAs($owner)->post(route('admin.google-ads.suggestions', $website), $data)
        ->assertRedirect(route('admin.billing.index'));
});
