<?php

use App\Models\ContentRequest;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->website = Website::factory()->for($this->owner, 'owner')->create();
    WebsiteRepository::factory()->for($this->website)->create();
    $this->actingAs($this->owner);
    $this->contentUrl = route('admin.websites.section', [$this->website, 'content']);
});

function contentWorkspaceDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML($html);

    return new DOMXPath($document);
}

test('content opens the queue first and keeps settings and history out of the visible layout', function () {
    $response = $this->get($this->contentUrl)->assertSuccessful();
    $xpath = contentWorkspaceDocument($response->getContent());
    expect($xpath->query('//*[@id="content-section-queue" and not(@hidden)]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="content-section-activity" and @hidden]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="content-section-automation" and @hidden]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="content-section-connections" and @hidden]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="content-section-queue"]//details[not(@open)]')->length)->toBe(1);
    $response->assertSeeInOrder(['Content workspace', 'Waiting in the queue', 'Content sections', 'Content queue']);
});

test('content subsection URLs select one visible panel and preserve browser navigation', function (string $section) {
    $response = $this->get($this->contentUrl.'?content_section='.$section)->assertSuccessful();
    $xpath = contentWorkspaceDocument($response->getContent());
    expect($xpath->query('//*[@id="content-section-'.$section.'" and not(@hidden)]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="content-section-tab-'.$section.'" and @aria-current="page"]')->length)->toBe(1);
    foreach (array_diff(['queue', 'activity', 'automation', 'connections'], [$section]) as $hidden) {
        expect($xpath->query('//*[@id="content-section-'.$hidden.'" and @hidden]')->length)->toBe(1);
    }
})->with(['queue', 'activity', 'automation', 'connections']);

test('invalid content sections safely fall back to the queue', function () {
    $response = $this->get($this->contentUrl.'?content_section[]=invalid')->assertSuccessful();
    expect(contentWorkspaceDocument($response->getContent())->query('//*[@id="content-section-queue" and not(@hidden)]')->length)->toBe(1);
});

test('request validation opens the request form with its errors', function () {
    $this->from($this->contentUrl)->post(route('admin.content-requests.store', $this->website), ['instructions' => ''])
        ->assertRedirect($this->contentUrl)->assertSessionHasErrors('instructions');
    $validationErrors = (new ViewErrorBag)->put('default', new MessageBag(['instructions' => 'Please describe the content request.']));
    $response = $this->get($this->contentUrl)->assertSuccessful();
    $view = $this->view('admin.websites.partials.content', [...$response->original->getData(), 'currentWebsiteSection' => 'content', 'errors' => $validationErrors]);
    expect(contentWorkspaceDocument((string) $view)->query('//*[@id="content-section-queue"]//details[@open]')->length)->toBe(1);
});

test('saving automation stays in automation and validation preserves its section', function () {
    $url = $this->contentUrl.'?content_section=automation';
    $payload = ['content_section' => 'automation', 'enabled' => false, 'weekday' => 1, 'hour' => 9, 'timezone' => 'Europe/London', 'audience' => 'Local customers'];
    $this->from($url)->put(route('admin.content-plans.update', $this->website), $payload)->assertRedirect($url);
    $this->from($url)->put(route('admin.content-plans.update', $this->website), [...$payload, 'timezone' => 'invalid'])
        ->assertRedirect($url)->assertSessionHasErrors('timezone');
    $response = $this->get($url)->assertSuccessful();
    expect(contentWorkspaceDocument($response->getContent())->query('//*[@id="content-section-automation" and not(@hidden)]')->length)->toBe(1);
});

test('activity is paginated without losing access to older requests', function () {
    ContentRequest::factory()->count(23)->for($this->website)->for($this->owner, 'creator')->create(['picked_up_at' => now()]);
    $url = $this->contentUrl.'?content_section=activity';
    $this->get($url)->assertSuccessful()->assertViewHas('actionedContentRequests', fn ($requests) => $requests->count() === 20 && $requests->total() === 23)
        ->assertSee('content_activity_page=2', false)->assertSee('overflow-y-auto', false);
    $response = $this->get($url.'&content_activity_page=2')->assertSuccessful()->assertViewHas('actionedContentRequests', fn ($requests) => $requests->count() === 3);
    expect(contentWorkspaceDocument($response->getContent())->query('//*[@id="content-section-activity" and not(@hidden)]')->length)->toBe(1);
});

test('viewers can follow work without seeing request or automation controls', function () {
    $viewer = User::factory()->create();
    $this->website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $response = $this->actingAs($viewer)->get($this->contentUrl.'?content_section=automation')->assertSuccessful()
        ->assertDontSee('Save content plan')->assertDontSee('Add content request');
    $xpath = contentWorkspaceDocument($response->getContent());
    expect($xpath->query('//*[@id="content-section-tab-automation"]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="content-section-queue" and not(@hidden)]')->length)->toBe(1);
});

test('essential sites retain a locked preview with no active content controls', function () {
    $this->owner->update(['membership_tier' => 'essential']);
    $this->get($this->contentUrl)->assertSuccessful()->assertSee('Plan and request new content')
        ->assertDontSee('Save content plan')->assertDontSee('Add content request')->assertDontSee('id="content-section-tab-queue"', false);
});
