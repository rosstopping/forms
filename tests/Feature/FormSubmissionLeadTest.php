<?php

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Website;

it('allows an admin to update a lead status and assignment', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $website = Website::factory()->create(['user_id' => $admin->id]);
    $form = Form::factory()->create(['website_id' => $website->id]);
    $submission = FormSubmission::factory()->create([
        'website_id' => $website->id,
        'form_id' => $form->id,
    ]);

    $assignee = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.form-submissions.update', $submission), [
            'status' => 'qualified',
            'notes' => 'Follow up next week.',
            'assigned_to' => $assignee->id,
        ])
        ->assertRedirect(route('admin.form-submissions.show', $submission));

    $submission->refresh();

    expect($submission->status)->toBe('qualified')
        ->and($submission->notes)->toBe('Follow up next week.')
        ->and($submission->assigned_to)->toBe($assignee->id);
});

it('redirects back to the dashboard after a quick lead status update', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $website = Website::factory()->create(['user_id' => $admin->id]);
    $form = Form::factory()->create(['website_id' => $website->id]);
    $submission = FormSubmission::factory()->create([
        'website_id' => $website->id,
        'form_id' => $form->id,
    ]);

    $this->actingAs($admin)
        ->withHeader('referer', route('admin.dashboard'))
        ->put(route('admin.form-submissions.update', $submission), [
            'status' => 'contacted',
        ])
        ->assertRedirect(route('admin.dashboard'));
});

it('filters the lead inbox and hides spam by default', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create(['user_id' => $admin->id]);
    $form = Form::factory()->create(['website_id' => $website->id]);
    FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id, 'status' => 'qualified', 'data' => ['name' => 'Ada Lovelace', 'email' => 'ada@example.com']]);
    FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id, 'is_spam' => true, 'data' => ['name' => 'Ada Spam']]);

    $this->actingAs($admin)->get(route('admin.form-submissions.index', ['search' => 'Ada', 'status' => 'qualified']))
        ->assertOk()->assertSee('Ada Lovelace')->assertDontSee('Ada Spam');
});

it('remembers lead filters for the signed in user', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create(['user_id' => $admin->id]);
    $form = Form::factory()->create(['website_id' => $website->id]);
    FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id, 'status' => 'qualified', 'data' => ['name' => 'Remembered Lead']]);
    FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id, 'status' => 'new', 'data' => ['name' => 'Hidden Lead']]);

    $this->actingAs($admin)
        ->get(route('admin.form-submissions.index', ['status' => 'qualified']))
        ->assertOk()
        ->assertSessionHas('admin.lead_filters.'.$admin->id, ['status' => 'qualified']);

    $this->get(route('admin.form-submissions.index'))
        ->assertOk()
        ->assertSee('Remembered Lead')
        ->assertDontSee('Hidden Lead');

    $this->get(route('admin.form-submissions.index', ['reset_filters' => 1]))
        ->assertOk()
        ->assertSessionMissing('admin.lead_filters.'.$admin->id)
        ->assertSee('Hidden Lead');
});

it('shows leads in the primary navigation', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create(['user_id' => $admin->id]);
    $form = Form::factory()->create(['website_id' => $website->id]);
    FormSubmission::factory()->count(3)->create(['website_id' => $website->id, 'form_id' => $form->id, 'status' => 'new']);
    FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id, 'status' => 'contacted']);
    FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id, 'status' => 'new', 'is_spam' => true]);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.form-submissions.index'), false)
        ->assertSee('Leads')
        ->assertSee('aria-label="3 new leads"', false);
});

it('scopes the new lead navigation count to accessible websites', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $owner->id]);
    $form = Form::factory()->create(['website_id' => $website->id]);
    FormSubmission::factory()->count(2)->create(['website_id' => $website->id, 'form_id' => $form->id, 'status' => 'new']);
    FormSubmission::factory()->create(['status' => 'new']);

    $this->actingAs($owner)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('aria-label="2 new leads"', false)
        ->assertDontSee('aria-label="3 new leads"', false);
});

it('bulk updates statuses marks spam and deletes selected leads', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $owner->id]);
    $form = Form::factory()->create(['website_id' => $website->id]);
    $statusLead = FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id]);
    $spamLead = FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id]);
    $deletedLead = FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id]);

    $this->actingAs($owner)->get(route('admin.form-submissions.index'))
        ->assertOk()
        ->assertSee('data-bulk-leads-actions class="hidden', false)
        ->assertSee('data-bulk-leads-selection-menu', false)
        ->assertSee('Select this page')
        ->assertSee('Select all')
        ->assertSee('data-bulk-leads-dialog', false)
        ->assertSee('data-bulk-leads-status-field', false)
        ->assertDontSee('>Apply</button>', false);

    $this->patch(route('admin.form-submissions.bulk'), [
        'submission_ids' => [$statusLead->id],
        'selection_scope' => 'page',
        'action' => 'update_status',
        'status' => 'won',
    ])->assertRedirect();

    $this->patch(route('admin.form-submissions.bulk'), [
        'submission_ids' => [$spamLead->id],
        'selection_scope' => 'page',
        'action' => 'mark_spam',
    ])->assertRedirect();

    $this->patch(route('admin.form-submissions.bulk'), [
        'submission_ids' => [$deletedLead->id],
        'selection_scope' => 'page',
        'action' => 'delete',
    ])->assertRedirect();

    expect($statusLead->refresh()->status)->toBe('won')
        ->and($spamLead->refresh()->is_spam)->toBeTrue();
    $this->assertModelMissing($deletedLead);
});

it('rejects a bulk action when any selected lead is not manageable', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $managedWebsite = Website::factory()->create(['user_id' => $owner->id]);
    $managedWebsite->members()->attach($member, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $managedForm = Form::factory()->create(['website_id' => $managedWebsite->id]);
    $managedLead = FormSubmission::factory()->create(['website_id' => $managedWebsite->id, 'form_id' => $managedForm->id]);
    $privateWebsite = Website::factory()->create();
    $privateForm = Form::factory()->create(['website_id' => $privateWebsite->id]);
    $privateLead = FormSubmission::factory()->create(['website_id' => $privateWebsite->id, 'form_id' => $privateForm->id]);

    $this->actingAs($member)->patch(route('admin.form-submissions.bulk'), [
        'submission_ids' => [$managedLead->id, $privateLead->id],
        'selection_scope' => 'page',
        'action' => 'mark_spam',
    ])->assertForbidden();

    expect($managedLead->refresh()->is_spam)->toBeFalse()
        ->and($privateLead->refresh()->is_spam)->toBeFalse();
});

it('bulk updates all manageable leads matching the active filters', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $owner->id]);
    $form = Form::factory()->create(['website_id' => $website->id]);
    $matchingLeads = FormSubmission::factory()->count(3)->create([
        'website_id' => $website->id,
        'form_id' => $form->id,
        'status' => 'new',
    ]);
    $excludedLead = FormSubmission::factory()->create([
        'website_id' => $website->id,
        'form_id' => $form->id,
        'status' => 'qualified',
    ]);

    $this->actingAs($owner)->patch(route('admin.form-submissions.bulk'), [
        'selection_scope' => 'all',
        'action' => 'update_status',
        'status' => 'contacted',
        'filter_status' => 'new',
        'spam' => 'exclude',
    ])->assertRedirect();

    expect($matchingLeads->each->refresh()->pluck('status')->unique()->all())->toBe(['contacted'])
        ->and($excludedLead->refresh()->status)->toBe('qualified');
});

it('lets a website owner configure the site wide automatic reply', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->put(route('admin.websites.autoresponder.update', $website), [
        'autoresponder_enabled' => true,
        'autoresponder_subject' => 'We received your enquiry',
        'autoresponder_body' => 'Thanks {name}. We will be in touch.',
    ])->assertRedirect(route('admin.websites.show', $website));

    expect($website->refresh()->autoresponder_enabled)->toBeTrue()
        ->and($website->autoresponder_subject)->toBe('We received your enquiry');
});
