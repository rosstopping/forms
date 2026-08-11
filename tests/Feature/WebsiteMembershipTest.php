<?php

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Website;

it('allows an owner to add update and remove a website member', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $owner->id, 'name' => 'Shared client website']);

    $this->actingAs($owner)->post(route('admin.websites.members.store', $website), [
        'user_id' => $member->id,
        'role' => Website::MEMBER_ROLE_VIEWER,
    ])->assertRedirect();

    expect($website->membershipRoleFor($member))->toBe(Website::MEMBER_ROLE_VIEWER);

    $this->put(route('admin.websites.members.update', [$website, $member]), [
        'role' => Website::MEMBER_ROLE_MANAGER,
    ])->assertRedirect();

    expect($website->membershipRoleFor($member))->toBe(Website::MEMBER_ROLE_MANAGER);

    $this->delete(route('admin.websites.members.destroy', [$website, $member]))->assertRedirect();

    expect($website->members()->whereKey($member->id)->exists())->toBeFalse();
});

it('allows managers to work with a shared website and its leads', function (): void {
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $owner->id, 'name' => 'Managed website']);
    $website->members()->attach($manager, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $form = Form::factory()->create(['website_id' => $website->id]);
    $submission = FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id]);

    $this->actingAs($manager)->get(route('admin.websites.index'))->assertOk()->assertSee($website->name);
    $this->get(route('admin.websites.show', $website))->assertOk();
    $this->get(route('admin.form-submissions.index'))->assertOk()->assertSee($submission->displayName());
    $this->put(route('admin.form-submissions.update', $submission), ['status' => 'contacted'])->assertRedirect();

    expect($submission->refresh()->status)->toBe('contacted');
});

it('gives viewers read only access', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $owner->id]);
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $form = Form::factory()->create(['website_id' => $website->id]);
    $submission = FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id]);

    $this->actingAs($viewer)->get(route('admin.websites.show', $website))->assertOk();
    $this->get(route('admin.form-submissions.show', $submission))->assertOk();
    $this->put(route('admin.form-submissions.update', $submission), ['status' => 'won'])->assertForbidden();
    $this->put(route('admin.websites.autoresponder.update', $website), [
        'autoresponder_enabled' => true,
    ])->assertForbidden();
    $this->post(route('admin.websites.members.store', $website), [
        'user_id' => User::factory()->create()->id,
        'role' => Website::MEMBER_ROLE_MANAGER,
    ])->assertForbidden();
});

it('keeps unrelated websites isolated from members', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $sharedWebsite = Website::factory()->create(['user_id' => $owner->id, 'name' => 'Shared website']);
    $privateWebsite = Website::factory()->create(['name' => 'Private website']);
    $sharedWebsite->members()->attach($member, ['role' => Website::MEMBER_ROLE_MANAGER]);

    $this->actingAs($member)->get(route('admin.websites.index'))
        ->assertOk()
        ->assertSee($sharedWebsite->name)
        ->assertDontSee($privateWebsite->name);

    $this->get(route('admin.websites.show', $privateWebsite))->assertForbidden();
});
