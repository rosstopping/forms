<?php

use App\Models\User;
use App\Models\Website;
use App\Services\WebsiteMailRecipients;

it('suppresses report recipients without disabling website health or form email settings', function (): void {
    config(['forms.report_emails_enabled' => false]);
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create([
        'health_reports_enabled' => true,
        'email_enabled' => true,
    ]);

    expect(app(WebsiteMailRecipients::class)->forReports($website))->toBe([])
        ->and($website->fresh()->health_reports_enabled)->toBeTrue()
        ->and($website->fresh()->email_enabled)->toBeTrue();
});
