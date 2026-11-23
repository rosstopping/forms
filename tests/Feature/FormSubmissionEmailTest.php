<?php

use App\Mail\FormSubmissionReceived;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Website;

beforeEach(function (): void {
    $website = Website::factory()->create(['name' => 'Rowglo']);
    $form = Form::factory()->for($website)->create(['name' => 'Contact form']);
    $this->submission = FormSubmission::factory()->for($website)->for($form)->create([
        'data' => [
            'name' => 'Example customer',
            'email' => 'customer@example.com',
            'choose_enquiry_type' => 'Boiler Repair',
            'gdpr' => 'Yes',
            'message' => "Please check my boiler.\nNo hot water. <script>alert(1)</script>",
        ],
        'source_domain' => 'rowglo.co.uk',
    ]);
});

it('brands enquiry emails with the website and puts submitted data before metadata', function (): void {
    $html = (new FormSubmissionReceived($this->submission))->render();

    expect($html)->toContain('<title>Rowglo</title>', 'Example customer', 'Enquiry type', 'Submission details', 'Mark as spam')
        ->not->toContain('class="brand-dot"', '>sitewell', '<title>Sitewell</title>', 'View enquiry', '/admin/form-submissions/', '<script>alert(1)</script>')
        ->and(strpos($html, 'Example customer'))->toBeLessThan(strpos($html, 'Submission details'))
        ->and(strpos($html, 'Please check my boiler.'))->toBeLessThan(strpos($html, 'IP address:'))
        ->and(strpos($html, 'Privacy consent'))->toBeGreaterThan(strpos($html, 'Submission details'));
});

it('sends from the fixed mail address under the website name and keeps customer replies', function (): void {
    config(['mail.from.address' => 'ross@digizu.co.uk']);
    $email = new FormSubmissionReceived($this->submission);
    $envelope = $email->envelope();

    expect($envelope->from->address)->toBe('mail@digizu.co.uk')
        ->and($envelope->from->name)->toBe('Rowglo')
        ->and($envelope->replyTo[0]->address)->toBe('customer@example.com');
});

it('uses the mail address for replies when the form has no email field', function (): void {
    $this->submission->update(['data' => ['name' => 'Example customer', 'message' => 'Please call me.']]);
    $envelope = (new FormSubmissionReceived($this->submission))->envelope();

    expect($envelope->replyTo[0]->address)->toBe('mail@digizu.co.uk');
});
