<?php

use App\Mail\FormSubmissionReceived;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Website;
use Illuminate\Support\Facades\URL;

function spamConfirmationUrl(FormSubmission $submission): string
{
    return URL::temporarySignedRoute(
        'form-submissions.spam.confirm',
        now()->addDays(30),
        ['formSubmission' => $submission],
    );
}

it('includes a signed mark as spam link in submission emails', function (): void {
    $website = Website::factory()->create();
    $form = Form::factory()->for($website)->create();
    $submission = FormSubmission::factory()->for($website)->for($form)->create();

    (new FormSubmissionReceived($submission))
        ->assertSeeInHtml('Mark as spam')
        ->assertSeeInHtml('/form-submissions/'.$submission->id.'/spam', false);
});

it('requires confirmation before marking a submission as spam', function (): void {
    $submission = FormSubmission::factory()->create(['is_spam' => false]);

    $this->get(spamConfirmationUrl($submission))
        ->assertSuccessful()
        ->assertSee('Mark submission as spam');

    expect($submission->fresh()->is_spam)->toBeFalse();
});

it('marks a submission as spam from its signed confirmation page', function (): void {
    $submission = FormSubmission::factory()->create(['is_spam' => false]);
    $url = spamConfirmationUrl($submission);

    $this->post($url)
        ->assertRedirect($url)
        ->assertSessionHas('status', 'This submission has been marked as spam.');

    expect($submission->fresh()->is_spam)->toBeTrue();
});

it('rejects an unsigned request to mark a submission as spam', function (): void {
    $submission = FormSubmission::factory()->create(['is_spam' => false]);

    $this->post(route('form-submissions.spam.store', $submission))->assertForbidden();

    expect($submission->fresh()->is_spam)->toBeFalse();
});
