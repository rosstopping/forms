<?php

use App\Models\BusinessProfileConnection;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->website = Website::factory()->for($this->owner, 'owner')->create();
    $this->profile = BusinessProfileConnection::factory()->for($this->website)->create();
    Http::preventStrayRequests();
    Http::fake(['https://mybusiness.googleapis.com/v4/*/localPosts' => Http::response(['name' => 'accounts/123/locations/456/localPosts/photo'])]);
    $this->actingAs($this->owner);
});

test('approved posts send their photo and button to Google and retain the photo', function (string $status) {
    $post = $this->profile->posts()->create(['status' => $status]);
    $photo = 'https://example.com/yacht.webp';
    $this->put(route('admin.business-profile.posts.update', [$this->website, $post]), [
        'summary' => 'Plan your Zante holiday.', 'photo_url' => $photo,
        'call_to_action_type' => 'LEARN_MORE', 'call_to_action_url' => 'https://example.com/packages',
    ])->assertRedirect()->assertSessionHasNoErrors();
    Http::assertSent(fn (Request $request) => $request['media'] === [['mediaFormat' => 'PHOTO', 'sourceUrl' => $photo]]
        && $request['summary'] === 'Plan your Zante holiday.'
        && $request['callToAction'] === ['actionType' => 'LEARN_MORE', 'url' => 'https://example.com/packages']);
    expect($post->fresh()->photo_url)->toBe($photo)->and($post->fresh()->status)->toBe('published');
})->with(['pending_approval', 'failed']);

test('text-only posts omit media', function () {
    $post = $this->profile->posts()->create(['status' => 'pending_approval']);
    $this->put(route('admin.business-profile.posts.update', [$this->website, $post]), ['summary' => 'An update.', 'photo_url' => ''])->assertRedirect()->assertSessionHasNoErrors();
    Http::assertSent(fn (Request $request) => ! array_key_exists('media', $request->data()));
});

test('invalid photo links never publish', function (string $photo) {
    $post = $this->profile->posts()->create(['status' => 'pending_approval']);
    $this->put(route('admin.business-profile.posts.update', [$this->website, $post]), ['summary' => 'An update.', 'photo_url' => $photo])->assertSessionHasErrors('photo_url');
    Http::assertNothingSent();
    expect($post->fresh()->status)->toBe('pending_approval');
})->with(['http://example.com/photo.jpg', 'javascript:alert(1)', 'not a URL']);

test('photos cannot publish under another website or republish a published post', function () {
    $other = BusinessProfileConnection::factory()->create()->posts()->create(['status' => 'pending_approval']);
    $this->put(route('admin.business-profile.posts.update', [$this->website, $other]), ['summary' => 'An update.', 'photo_url' => 'https://example.com/photo.jpg'])->assertUnprocessable();
    $published = $this->profile->posts()->create(['status' => 'published']);
    $this->put(route('admin.business-profile.posts.update', [$this->website, $published]), ['summary' => 'An update.'])->assertUnprocessable();
    Http::assertNothingSent();
});

test('failed drafts show a manual publishing form with an optional photo', function () {
    $this->profile->posts()->create(['status' => 'failed']);
    $this->get(route('admin.websites.section', [$this->website, 'business-profile']))->assertSuccessful()->assertSee('Photo link (optional)')->assertSee('Approve & publish', false);
    Http::assertNothingSent();
});

test('customer accounts cannot publish photos', function () {
    $customer = User::factory()->create();
    $website = Website::factory()->for($customer, 'owner')->create();
    $post = BusinessProfileConnection::factory()->for($website)->create()->posts()->create(['status' => 'pending_approval']);
    $this->actingAs($customer)->put(route('admin.business-profile.posts.update', [$website, $post]), ['summary' => 'An update.', 'photo_url' => 'https://example.com/photo.jpg'])->assertForbidden();
    Http::assertNothingSent();
});
