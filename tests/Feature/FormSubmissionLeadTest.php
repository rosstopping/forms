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

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.form-submissions.index'), false)
        ->assertSee('Leads');
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
