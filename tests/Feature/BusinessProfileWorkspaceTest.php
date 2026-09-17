<?php

use App\Ai\Agents\BusinessProfilePostWriter;
use App\Ai\Agents\BusinessProfileReviewResponder;
use App\Jobs\GenerateBusinessProfilePost;
use App\Jobs\GenerateBusinessProfileReviewReply;
use App\Jobs\SyncBusinessProfileReviews;
use App\Models\BusinessProfileConnection;
use App\Models\BusinessProfilePost;
use App\Models\BusinessProfileReview;
use App\Models\User;
use App\Models\Website;
use App\Services\BusinessProfileClient;
use App\Services\BusinessProfilePostSuggestions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    $this->owner = User::factory()->create();
    $this->website = Website::factory()->for($this->owner, 'owner')->create();
    $this->profile = BusinessProfileConnection::factory()->for($this->website)->create();
    $this->workspace = route('admin.websites.section', [$this->website, 'business-profile']);
    $this->actingAs($this->owner);
});

test('suggested topics enter the queue once without drafting or publishing', function () {
    $url = route('admin.business-profile.posts.store', $this->website);
    $this->from($this->workspace)->post($url, ['suggestion' => 'introduction'])->assertRedirect($this->workspace);
    $this->post($url, ['suggestion' => 'introduction'])->assertRedirect();
    $post = $this->profile->posts()->sole();
    expect($post->status)->toBe('queued')->and($post->topic)->toContain($this->profile->location_title)->and($post->published_at)->toBeNull();
    Queue::assertNotPushed(GenerateBusinessProfilePost::class);
    Http::assertNothingSent();
});

test('custom ideas require an explicit topic and reject invented suggestion keys', function () {
    $url = route('admin.business-profile.posts.store', $this->website);
    $this->post($url, [])->assertSessionHasErrors('topic');
    $this->post($url, ['suggestion' => 'invented'])->assertSessionHasErrors('suggestion');
    $this->post($url, ['topic' => 'Our new opening hours start on 10 October.'])->assertRedirect();
    expect($this->profile->posts()->sole()->topic)->toBe('Our new opening hours start on 10 October.');
});

test('suggestions use stored profile facts and generation receives that context', function () {
    $this->profile->audits()->create(['status' => 'completed', 'snapshot' => [
        'profile' => ['description' => 'Bicycle repairs in Bristol.'],
        'categories' => ['primaryCategory' => ['displayName' => 'Bicycle repair shop']],
        'regularHours' => ['periods' => [['openDay' => 'MONDAY']]],
        'websiteUri' => 'https://bikes.example.com',
    ]]);
    $ideas = app(BusinessProfilePostSuggestions::class)->forConnection($this->profile);
    expect($ideas)->toHaveKeys(['introduction', 'category', 'visit', 'website']);
    expect($ideas['category']['description'])->toContain('Bicycle repair shop');
    BusinessProfilePostWriter::fake([['summary' => 'Bicycle repairs in Bristol.', 'call_to_action_type' => 'NONE', 'call_to_action_url' => null]]);
    $post = $this->profile->posts()->create(['status' => 'generating', 'topic' => $ideas['category']['topic']]);
    (new GenerateBusinessProfilePost($post))->handle(app(BusinessProfilePostWriter::class));
    BusinessProfilePostWriter::assertPrompted(fn ($prompt) => $prompt->contains('Bicycle repairs in Bristol.'));
    expect($post->fresh()->status)->toBe('pending_approval');
    Http::assertNothingSent();
});

test('queued posts can be drafted now but duplicate requests do not dispatch twice', function () {
    $post = $this->profile->posts()->create(['status' => 'queued', 'topic' => 'Our business']);
    $url = route('admin.business-profile.posts.draft', [$this->website, $post]);
    $this->post($url)->assertRedirect();
    $this->post($url)->assertUnprocessable();
    expect($post->fresh()->status)->toBe('generating');
    Queue::assertPushed(GenerateBusinessProfilePost::class, 1);
});

test('queued and approval posts can be removed without affecting published posts', function () {
    $post = $this->profile->posts()->create(['status' => 'queued', 'topic' => 'Remove me']);
    $this->delete(route('admin.business-profile.posts.destroy', [$this->website, $post]))->assertRedirect();
    expect($post->fresh())->toBeNull();
    $published = $this->profile->posts()->create(['status' => 'published', 'summary' => 'Live']);
    $this->delete(route('admin.business-profile.posts.destroy', [$this->website, $published]))->assertUnprocessable();
});

test('weekly schedule drafts the oldest topic only once in the scheduled local day', function () {
    $this->travelTo(now()->setDate(2026, 9, 21)->setTime(23, 0));
    $this->profile->update(['weekly_posts_enabled' => true, 'post_weekday' => 2, 'post_hour' => 0, 'timezone' => 'Europe/London']);
    $first = $this->profile->posts()->create(['status' => 'queued', 'topic' => 'First']);
    $second = $this->profile->posts()->create(['status' => 'queued', 'topic' => 'Second']);
    $this->artisan('business-profiles:dispatch-audits')->assertSuccessful();
    $this->artisan('business-profiles:dispatch-audits')->assertSuccessful();
    expect($first->fresh()->status)->toBe('generating')->and($second->fresh()->status)->toBe('queued');
    expect($this->profile->fresh()->last_post_scheduled_at)->not->toBeNull();
    Queue::assertPushed(GenerateBusinessProfilePost::class, 1);
});

test('empty queues do not generate unspecified posts', function () {
    $local = now('Europe/London');
    $this->profile->update(['weekly_posts_enabled' => true, 'post_weekday' => $local->dayOfWeek, 'post_hour' => $local->hour]);
    $this->artisan('business-profiles:dispatch-audits')->assertSuccessful();
    expect($this->profile->posts()->count())->toBe(0);
    Queue::assertNotPushed(GenerateBusinessProfilePost::class);
});

test('dispatcher skips incomplete and ineligible profiles', function () {
    $this->profile->update(['location_name' => null]);
    $this->artisan('business-profiles:dispatch-audits')->assertSuccessful();
    Queue::assertNothingPushed();
    $this->profile->update(['location_name' => 'locations/456']);
    $this->owner->update(['membership_tier' => 'essential']);
    $this->artisan('business-profiles:dispatch-audits')->assertSuccessful();
    Queue::assertNothingPushed();
});

test('review sync follows all pages and preserves prepared replies', function () {
    $draft = $this->profile->reviews()->create(['google_review_name' => 'accounts/123/locations/456/reviews/old', 'star_rating' => 5, 'reply_status' => 'pending_approval', 'suggested_reply' => 'Already prepared.']);
    Http::fake(['https://mybusiness.googleapis.com/v4/*/reviews*' => Http::sequence()
        ->push(['reviews' => [['name' => $draft->google_review_name, 'starRating' => 'FIVE']], 'nextPageToken' => 'page-two'])
        ->push(['reviews' => [['name' => 'accounts/123/locations/456/reviews/new', 'starRating' => 'ONE', 'comment' => 'Disappointed.']]])]);
    app(BusinessProfileClient::class)->syncReviews($this->profile);
    expect($draft->fresh()->reply_status)->toBe('pending_approval')->and($draft->fresh()->suggested_reply)->toBe('Already prepared.');
    expect($this->profile->reviews()->where('reply_status', 'generating')->count())->toBe(1);
    Queue::assertPushed(GenerateBusinessProfileReviewReply::class, 1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'pageToken=page-two'));
});

test('repeated background sync does not regenerate drafts or lose published replies', function () {
    $generating = $this->profile->reviews()->create(['google_review_name' => 'accounts/123/locations/456/reviews/a', 'star_rating' => 5, 'reply_status' => 'generating']);
    $replied = $this->profile->reviews()->create(['google_review_name' => 'accounts/123/locations/456/reviews/b', 'star_rating' => 5, 'reply_status' => 'replied', 'google_reply' => 'Thank you!']);
    Http::fake(['https://mybusiness.googleapis.com/v4/*/reviews*' => Http::response(['reviews' => [
        ['name' => $generating->google_review_name, 'starRating' => 'FIVE'],
        ['name' => $replied->google_review_name, 'starRating' => 'FIVE'],
    ]])]);
    (new SyncBusinessProfileReviews($this->profile))->handle(app(BusinessProfileClient::class));
    expect($generating->fresh()->reply_status)->toBe('generating')->and($replied->fresh()->google_reply)->toBe('Thank you!')->and($replied->fresh()->reply_status)->toBe('replied');
    Queue::assertNotPushed(GenerateBusinessProfileReviewReply::class);
});

test('bulk preparation handles all unanswered and failed reviews once and never publishes', function () {
    foreach (['unanswered', 'failed', 'pending_approval', 'replied', 'generating'] as $status) {
        $this->profile->reviews()->create(['google_review_name' => 'reviews/'.$status, 'star_rating' => 1, 'reply_status' => $status]);
    }
    $url = route('admin.business-profile.reviews.drafts', $this->website);
    $this->post($url)->assertRedirect();
    $this->post($url)->assertRedirect();
    Queue::assertPushed(GenerateBusinessProfileReviewReply::class, 2);
    Http::assertNothingSent();
});

test('automatically generated replies to every rating wait for approval', function (int $rating) {
    BusinessProfileReviewResponder::fake([['reply' => 'Thank you for sharing your feedback.']]);
    $review = $this->profile->reviews()->create(['google_review_name' => 'reviews/new', 'star_rating' => $rating, 'reply_status' => 'generating']);
    (new GenerateBusinessProfileReviewReply($review))->handle(app(BusinessProfileReviewResponder::class));
    expect($review->fresh()->reply_status)->toBe('pending_approval')->and($review->fresh()->google_reply)->toBeNull()->and($review->fresh()->approved_at)->toBeNull();
    Http::assertNothingSent();
})->with([1, 2, 3, 4, 5]);

test('stale reply jobs cannot overwrite a reply already published on Google', function () {
    BusinessProfileReviewResponder::fake();
    $review = $this->profile->reviews()->create(['google_review_name' => 'reviews/done', 'star_rating' => 5, 'reply_status' => 'replied', 'google_reply' => 'Thanks!']);
    (new GenerateBusinessProfileReviewReply($review))->handle(app(BusinessProfileReviewResponder::class));
    BusinessProfileReviewResponder::assertNeverPrompted();
    expect($review->fresh()->reply_status)->toBe('replied');
});

test('workspace combines suggestions scheduling and a paginated scrollable review inbox', function () {
    BusinessProfileReview::factory()->count(12)->for($this->profile, 'connection')->create(['reply_status' => 'pending_approval']);
    $this->get($this->workspace)->assertSuccessful()->assertSeeInOrder(['Posts and automation', 'Suggested posts', 'Post queue', 'Your automation', 'Review inbox'])
        ->assertSee('Automatic review drafts are on')->assertSee('overflow-y-auto', false)->assertSee('bp_reviews_page=2', false)
        ->assertDontSee('Generate reply draft')->assertViewHas('businessReviews', fn ($reviews) => $reviews->count() === 10 && $reviews->total() === 12);
    $this->get($this->workspace.'?bp_reviews_page=2')->assertSuccessful()->assertViewHas('businessReviews', fn ($reviews) => $reviews->count() === 2);
});

test('viewers see the workspace without publishing or automation controls', function () {
    $viewer = User::factory()->create();
    $this->website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($viewer)->get($this->workspace)->assertSuccessful()->assertDontSee('Save automation')->assertDontSee('Add to queue')->assertDontSee('Sync reviews');
    $this->post(route('admin.business-profile.posts.store', $this->website), ['topic' => 'Not allowed'])->assertForbidden();
    $this->post(route('admin.business-profile.reviews.drafts', $this->website))->assertForbidden();
});

test('queue actions cannot act on another websites posts', function () {
    $post = BusinessProfilePost::factory()->create(['status' => 'queued']);
    $this->post(route('admin.business-profile.posts.draft', [$this->website, $post]))->assertNotFound();
    $this->delete(route('admin.business-profile.posts.destroy', [$this->website, $post]))->assertNotFound();
    expect($post->fresh()->status)->toBe('queued');
});

test('incomplete profiles redirect queue and bulk draft actions to location selection', function () {
    $this->profile->update(['location_name' => null]);
    $this->post(route('admin.business-profile.posts.store', $this->website), ['topic' => 'Welcome'])->assertRedirect(route('admin.business-profile.locations', $this->website));
    $this->post(route('admin.business-profile.reviews.drafts', $this->website))->assertRedirect(route('admin.business-profile.locations', $this->website));
    Queue::assertNothingPushed();
});
