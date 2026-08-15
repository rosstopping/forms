<?php

use App\Ai\Agents\BusinessProfilePostWriter;
use App\Jobs\AuditBusinessProfile;
use App\Jobs\GenerateBusinessProfilePost;
use App\Jobs\GenerateBusinessProfileReviewReply;
use App\Models\BusinessProfileAudit;
use App\Models\BusinessProfileConnection;
use App\Models\BusinessProfilePost;
use App\Models\BusinessProfileRecommendation;
use App\Models\BusinessProfileReview;
use App\Models\User;
use App\Models\Website;
use App\Services\BusinessProfileClient;
use App\Services\BusinessProfileOAuthClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.google.client_id' => 'client', 'services.google.client_secret' => 'secret']);
});

test('business profile oauth requests offline management access', function () {
    $url = app(BusinessProfileOAuthClient::class)->authorizationUrl('secure-state');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toMatchArray(['scope' => 'https://www.googleapis.com/auth/business.manage', 'access_type' => 'offline', 'prompt' => 'consent', 'state' => 'secure-state']);
});

test('review sync redirects to location selection instead of throwing when no location is selected', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    BusinessProfileConnection::factory()->for($website)->create([
        'account_name' => null,
        'location_name' => null,
        'location_title' => null,
    ]);

    $this->actingAs($owner)
        ->post(route('admin.business-profile.reviews.sync', $website))
        ->assertRedirect(route('admin.business-profile.locations', $website))
        ->assertSessionHas('error', 'Select a Google Business Profile location before syncing reviews.');
});

test('business profile workspace offers selection and reconnection for incomplete connections', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    BusinessProfileConnection::factory()->for($website)->create([
        'account_name' => null,
        'location_name' => null,
        'location_title' => null,
    ]);

    $this->actingAs($owner)
        ->get(route('admin.websites.show', [$website, 'tab' => 'business-profile']))
        ->assertSuccessful()
        ->assertSee('Select a location')
        ->assertSee('Reconnect Google')
        ->assertDontSee('Sync reviews');
});

test('review sync turns expired Google authorization errors into a reconnectable message', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    BusinessProfileConnection::factory()->for($website)->create();
    $client = $this->mock(BusinessProfileClient::class);
    $client->shouldReceive('syncReviews')->once()->andThrow(new RuntimeException('Google Business Profile authorization expired. Reconnect it to continue.'));

    $this->actingAs($owner)
        ->from(route('admin.websites.show', [$website, 'tab' => 'business-profile']))
        ->post(route('admin.business-profile.reviews.sync', $website))
        ->assertRedirect(route('admin.websites.show', [$website, 'tab' => 'business-profile']))
        ->assertSessionHas('error', 'Google Business Profile authorization expired. Reconnect it to continue.');
});

test('weekly dispatcher queues one audit and one post draft at most', function () {
    Queue::fake();
    $now = now('Europe/London');
    $connection = BusinessProfileConnection::factory()->create(['weekly_posts_enabled' => true, 'post_weekday' => $now->dayOfWeek, 'post_hour' => $now->hour, 'timezone' => 'Europe/London', 'last_synced_at' => now()]);

    $this->artisan('business-profiles:dispatch-audits')->assertSuccessful();
    $this->artisan('business-profiles:dispatch-audits')->assertSuccessful();

    expect($connection->audits()->count())->toBe(1)->and($connection->posts()->count())->toBe(1);
    Queue::assertPushed(AuditBusinessProfile::class, 1);
    Queue::assertPushed(GenerateBusinessProfilePost::class, 1);
});

test('review drafts remain pending until an owner explicitly approves them', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $connection = BusinessProfileConnection::factory()->for($website)->create();
    $review = $connection->reviews()->create(['google_review_name' => 'accounts/123/locations/456/reviews/789', 'reviewer_name' => 'Alex', 'star_rating' => 5, 'comment' => 'Excellent.', 'reply_status' => BusinessProfileReview::STATUS_UNANSWERED]);

    $this->actingAs($owner)->post(route('admin.business-profile.reviews.draft', [$website, $review]))->assertRedirect();

    expect($review->fresh()->reply_status)->toBe(BusinessProfileReview::STATUS_GENERATING)->and($review->fresh()->google_reply)->toBeNull();
    Queue::assertPushed(GenerateBusinessProfileReviewReply::class);
});

test('approved review replies and posts are sent to Google', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $connection = BusinessProfileConnection::factory()->for($website)->create();
    $review = $connection->reviews()->create(['google_review_name' => 'accounts/123/locations/456/reviews/789', 'star_rating' => 5, 'comment' => 'Excellent.', 'suggested_reply' => 'Thank you!', 'reply_status' => BusinessProfileReview::STATUS_PENDING_APPROVAL]);
    $post = $connection->posts()->create(['status' => BusinessProfilePost::STATUS_PENDING_APPROVAL, 'summary' => 'Come and see us.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://mybusiness.googleapis.com/v4/*/reply' => Http::response([]),
        'https://mybusiness.googleapis.com/v4/*/localPosts' => Http::response(['name' => 'accounts/123/locations/456/localPosts/1']),
    ]);

    $this->actingAs($owner)->put(route('admin.business-profile.reviews.update', [$website, $review]), ['reply' => 'Thank you!'])->assertRedirect();
    $this->actingAs($owner)->put(route('admin.business-profile.posts.update', [$website, $post]), ['summary' => 'Come and see us.', 'call_to_action_type' => null, 'call_to_action_url' => null])->assertRedirect();

    expect($review->fresh()->reply_status)->toBe(BusinessProfileReview::STATUS_REPLIED)->and($post->fresh()->status)->toBe(BusinessProfilePost::STATUS_PUBLISHED);
    Http::assertSentCount(2);
});

test('profile recommendations require approval before applying changes', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $connection = BusinessProfileConnection::factory()->for($website)->create();
    $audit = $connection->audits()->create(['status' => BusinessProfileAudit::STATUS_COMPLETED]);
    $recommendation = $audit->recommendations()->create(['key' => 'website', 'title' => 'Add website', 'description' => 'Add it', 'field_mask' => 'websiteUri', 'proposed_value' => ['websiteUri' => 'https://example.com'], 'status' => BusinessProfileRecommendation::STATUS_PENDING]);
    $client = $this->mock(BusinessProfileClient::class);
    $client->shouldReceive('updateLocation')->once();

    $this->actingAs($owner)->put(route('admin.business-profile.recommendations.update', [$website, $recommendation]))->assertRedirect();

    expect($recommendation->fresh()->status)->toBe(BusinessProfileRecommendation::STATUS_APPLIED)->and($recommendation->fresh()->approved_by)->toBe($owner->id);
});

test('AI generated posts always stop for approval', function () {
    BusinessProfilePostWriter::fake([['summary' => 'A useful local update.', 'call_to_action_type' => 'NONE', 'call_to_action_url' => null]]);
    $post = BusinessProfileConnection::factory()->create()->posts()->create(['status' => BusinessProfilePost::STATUS_GENERATING, 'topic' => 'Share an update']);

    (new GenerateBusinessProfilePost($post))->handle(app(BusinessProfilePostWriter::class));

    expect($post->fresh()->status)->toBe(BusinessProfilePost::STATUS_PENDING_APPROVAL)
        ->and($post->fresh()->summary)->toBe('A useful local update.')
        ->and($post->fresh()->published_at)->toBeNull();
});
