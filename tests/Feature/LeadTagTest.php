<?php

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\LeadTag;
use App\Models\User;
use App\Models\Website;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->website = Website::factory()->for($this->owner, 'owner')->create();
    $this->form = Form::factory()->for($this->website)->create();
    $this->lead = FormSubmission::factory()->for($this->website)->for($this->form)->create(['status' => 'new', 'data' => ['name' => 'Tagged Person']]);
    $this->owner->update(['current_website_id' => $this->website->id]);
    $this->actingAs($this->owner);
});

it('creates a reusable website tag and records its assignment', function () {
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'new', 'new_tag' => '  Quote   requested  ', 'tags_present' => true])
        ->assertSessionDoesntHaveErrors();
    $tag = $this->lead->tags()->sole();
    expect($tag->name)->toBe('Quote requested')->and($tag->website_id)->toBe($this->website->id)
        ->and($tag->normalized_name)->toBe('quote requested')
        ->and($this->lead->activities()->where('type', 'tags_updated')->sole()->metadata)->toBe(['tags' => ['Quote requested']]);
    $this->get(route('admin.form-submissions.show', $this->lead))->assertSuccessful()->assertSee('Quote requested')->assertSee('New tag');
    $this->get(route('admin.form-submissions.index'))->assertSuccessful()->assertSee('Quote requested');
});

it('reuses existing tags regardless of case and avoids duplicate assignments', function () {
    $tag = LeadTag::factory()->for($this->website)->create(['name' => 'High priority']);
    $data = ['status' => 'new', 'tag_ids' => [$tag->id], 'new_tag' => 'HIGH PRIORITY'];
    $this->put(route('admin.form-submissions.update', $this->lead), $data)->assertSessionDoesntHaveErrors();
    $this->put(route('admin.form-submissions.update', $this->lead), $data)->assertSessionDoesntHaveErrors();
    expect(LeadTag::count())->toBe(1)->and($this->lead->tags()->count())->toBe(1)
        ->and($this->lead->activities()->where('type', 'tags_updated')->count())->toBe(1);
});

it('removes tags without deleting their website vocabulary and preserves tags on status-only updates', function () {
    $tag = LeadTag::factory()->for($this->website)->create();
    $this->lead->tags()->attach($tag);
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'contacted'])->assertSessionDoesntHaveErrors();
    expect($this->lead->tags()->count())->toBe(1);
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'contacted', 'tags_present' => true])->assertSessionDoesntHaveErrors();
    expect($this->lead->tags()->count())->toBe(0)->and(LeadTag::count())->toBe(1);
});

it('keeps the same tag name separate between websites', function () {
    $foreign = LeadTag::factory()->create(['name' => 'Quote requested']);
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'new', 'new_tag' => 'Quote requested'])->assertSessionDoesntHaveErrors();
    expect($this->lead->tags()->sole()->id)->not->toBe($foreign->id)->and(LeadTag::count())->toBe(2);
});

it('rejects foreign tag assignments even for an administrator', function () {
    $this->owner->update(['role' => User::ROLE_ADMIN]);
    $foreign = LeadTag::factory()->create();
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'won', 'tag_ids' => [$foreign->id]])->assertSessionHasErrors('tag_ids.0');
    expect($this->lead->fresh()->status)->toBe('new')->and($this->lead->tags()->count())->toBe(0);
    $this->get(route('admin.form-submissions.show', $this->lead))->assertDontSee($foreign->name);
});

it('allows shared managers to tag leads but leaves viewers read-only', function () {
    $manager = User::factory()->create();
    $viewer = User::factory()->create();
    $this->website->members()->attach($manager, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $this->website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($manager)->put(route('admin.form-submissions.update', $this->lead), ['status' => 'new', 'new_tag' => 'Existing customer'])->assertSessionDoesntHaveErrors();
    $this->actingAs($viewer)->put(route('admin.form-submissions.update', $this->lead), ['status' => 'new', 'tags_present' => true])->assertForbidden();
    $this->get(route('admin.form-submissions.show', $this->lead))->assertSuccessful()->assertSee('Existing customer')->assertDontSee('New tag')->assertDontSee('Save lead');
    expect($this->lead->tags()->count())->toBe(1);
});

it('rejects malformed and overlong tag input', function (array $input, string $error) {
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'new', ...$input])->assertSessionHasErrors($error);
    expect(LeadTag::count())->toBe(0);
    $this->get(route('admin.form-submissions.show', $this->lead))->assertSuccessful();
})->with([
    [['new_tag' => str_repeat('a', 41)], 'new_tag'],
    [['new_tag' => ['invalid']], 'new_tag'],
    [['tag_ids' => 'invalid'], 'tag_ids'],
    [['tag_ids' => [999]], 'tag_ids.0'],
]);

it('filters and remembers tags only for their website and clears the filter', function () {
    $tag = LeadTag::factory()->for($this->website)->create();
    $this->lead->tags()->attach($tag);
    FormSubmission::factory()->for($this->website)->for($this->form)->create(['data' => ['name' => 'Untagged Person']]);
    $this->get(route('admin.form-submissions.index', ['tag_id' => $tag->id]))->assertSuccessful()->assertSee('Tagged Person')->assertDontSee('Untagged Person');
    $this->get(route('admin.form-submissions.index'))->assertSuccessful()->assertDontSee('Untagged Person');
    $otherWebsite = Website::factory()->for($this->owner, 'owner')->create();
    $otherForm = Form::factory()->for($otherWebsite)->create();
    FormSubmission::factory()->for($otherWebsite)->for($otherForm)->create(['data' => ['name' => 'Other Site Person']]);
    $this->owner->update(['current_website_id' => $otherWebsite->id]);
    $this->get(route('admin.form-submissions.index'))->assertSuccessful()->assertSee('Other Site Person')->assertDontSee($tag->name);
    $this->owner->update(['current_website_id' => $this->website->id]);
    $this->get(route('admin.form-submissions.index', ['reset_filters' => 1]))->assertSuccessful()->assertSee('Untagged Person');
});

it('rejects filters for another website and invalid tag values', function () {
    $foreign = LeadTag::factory()->create();
    $this->get(route('admin.form-submissions.index', ['tag_id' => $foreign->id]))->assertSessionHasErrors('tag_id');
    $this->get(route('admin.form-submissions.index', ['tag_id' => ['invalid']]))->assertSessionHasErrors('tag_id');
});

it('applies tag filters to all-matching bulk actions', function () {
    $tag = LeadTag::factory()->for($this->website)->create();
    $this->lead->tags()->attach($tag);
    $untagged = FormSubmission::factory()->for($this->website)->for($this->form)->create(['status' => 'new']);
    $this->patch(route('admin.form-submissions.bulk'), ['selection_scope' => 'all', 'action' => 'update_status', 'status' => 'qualified', 'tag_id' => $tag->id])->assertSessionDoesntHaveErrors();
    expect($this->lead->fresh()->status)->toBe('qualified')->and($untagged->fresh()->status)->toBe('new');
    $foreign = LeadTag::factory()->create();
    $this->patch(route('admin.form-submissions.bulk'), ['selection_scope' => 'all', 'action' => 'delete', 'tag_id' => $foreign->id])->assertSessionHasErrors('tag_id');
    expect(FormSubmission::find($this->lead->id))->not->toBeNull();
});

it('escapes tag names and retains tag assignments after a lead validation error', function () {
    $tag = LeadTag::factory()->for($this->website)->create(['name' => '<script>alert(1)</script>']);
    $this->lead->tags()->attach($tag);
    $this->get(route('admin.form-submissions.show', $this->lead))->assertSee(e($tag->name), false)->assertDontSee($tag->name, false);
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'invalid', 'tags_present' => true])->assertSessionHasErrors('status');
    expect($this->lead->tags()->count())->toBe(1);
});

it('enforces the total tag limit including a newly created tag without partial changes', function () {
    $tags = LeadTag::factory()->count(20)->for($this->website)->create();
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'won', 'tag_ids' => $tags->modelKeys(), 'new_tag' => 'One too many'])
        ->assertSessionHasErrors('tag_ids');
    expect($this->lead->fresh()->status)->toBe('new')->and($this->lead->tags()->count())->toBe(0)
        ->and(LeadTag::where('name', 'One too many')->exists())->toBeFalse();
});
