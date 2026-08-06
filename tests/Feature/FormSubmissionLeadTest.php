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
