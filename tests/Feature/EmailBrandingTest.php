<?php

use App\Enums\OnboardingLifecycleStep;
use App\Mail\ContentGenerationReady;
use App\Mail\ContentSuggestionReminder;
use App\Mail\FormSubmissionAcknowledgement;
use App\Mail\FormSubmissionReceived;
use App\Mail\FreeSiteAuditResults;
use App\Mail\MonthlyRankingReport;
use App\Mail\OnboardingEnquiryReceived;
use App\Mail\ProspectOutreach;
use App\Mail\WebsiteAiQuestionReported;
use App\Mail\WebsiteHealthReportReady;
use App\Mail\WeeklyRankingReport;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\FormSubmission;
use App\Models\OnboardingLifecycleMessage;
use App\Models\Prospect;
use App\Models\SearchConsoleMetric;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAiQuestion;
use App\Models\WebsiteAudit;
use App\Models\WebsiteHealthReport;
use App\Notifications\OnboardingLifecycleNotification;
use App\Notifications\ProspectBecameHot;
use App\Notifications\WebsiteAuditClaim;
use App\Notifications\WebsiteInvitation;
use App\Services\RankingReportBuilder;
use Dom\HTMLDocument;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Markdown;

it('renders every Sitewell mailable with the shared inline email branding', function (string $kind): void {
    $website = Website::factory()->create(['name' => 'Acme website']);
    $prospect = Prospect::factory()->create(['outreach_body' => 'Hello from Ross.', 'outreach_subject' => 'Your website']);
    $report = app(RankingReportBuilder::class)->build($website);
    $report['latestSearch'] = SearchConsoleMetric::factory()->create(['clicks' => 1234, 'impressions' => 123456, 'ctr' => 0.01, 'position' => 12.3]);
    $mail = match ($kind) {
        'weekly' => new WeeklyRankingReport($website, $report),
        'monthly' => new MonthlyRankingReport($website, [...$report, 'queryWins' => collect(), 'queryDeclines' => collect()]),
        'health' => new WebsiteHealthReportReady(WebsiteHealthReport::factory()->for($website)->create()),
        'generation' => new ContentGenerationReady(ContentGeneration::factory()->create(['pull_request_number' => 12, 'pull_request_url' => 'https://github.com/example/site/pull/12'])),
        'suggestions' => new ContentSuggestionReminder(ContentPlan::factory()->for($website)->create(), new Collection, new Collection),
        'submission' => new FormSubmissionReceived(FormSubmission::factory()->for($website)->create()),
        'audit' => new FreeSiteAuditResults($prospect),
        'outreach' => new ProspectOutreach($prospect),
        'ai-report' => new WebsiteAiQuestionReported(WebsiteAiQuestion::factory()->for($website)->create()),
        'enquiry' => new OnboardingEnquiryReceived(['name' => 'Alex', 'email' => 'alex@example.com', 'agency' => null, 'website' => null, 'goals' => 'Help with my website.']),
    };
    $html = $mail->render();
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    expect($document->querySelector('.header')->getAttribute('style'))->toContain('#17201d')
        ->and($document->querySelector('.wrapper')->getAttribute('style'))->toContain('#f4f1e8')
        ->and($document->querySelector('.brand')->textContent)->toBe('Sitewell.')
        ->and($document->querySelectorAll('html')->length)->toBe(1)
        ->and($document->querySelector('.content-cell')->textContent)->not->toBeEmpty()
        ->and($html)->toContain('Your website, well looked after.', '@media only screen')
        ->not->toContain('<script', '&lt;table', 'notification-logo-v2.1.png');
    if ($kind === 'outreach') {
        expect($document->querySelectorAll('a')->length)->toBe(0);
    }
})->with(['weekly', 'monthly', 'health', 'generation', 'suggestions', 'submission', 'audit', 'outreach', 'ai-report', 'enquiry']);

it('brands account and admin notifications while preserving their action links', function (string $kind): void {
    $user = User::factory()->unverified()->create();
    $notification = match ($kind) {
        'invitation' => new WebsiteInvitation(Website::factory()->create(), true),
        'reset' => new ResetPassword('sample-reset-token'),
        'claim' => new WebsiteAuditClaim(WebsiteAudit::factory()->create()),
        'hot-prospect' => new ProspectBecameHot(Prospect::factory()->create(), 'Website audit'),
    };
    $mail = $notification->toMail($user);
    $html = (string) $mail->render();
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    expect($document->querySelector('.brand')->textContent)->toBe('Sitewell.')
        ->and($document->querySelector('.button')->getAttribute('href'))->toBe($mail->actionUrl)
        ->and($document->querySelector('.button')->getAttribute('style'))->toContain('#167a53')
        ->and($document->querySelector('.subcopy')->textContent)->toContain($mail->actionUrl)
        ->and($document->querySelector('.content-cell')->textContent)->toContain('The Sitewell team')->not->toContain('Laravel');

    expect((string) app(Markdown::class)->renderText($mail->markdown, $mail->data()))
        ->toContain('Sitewell', $mail->actionUrl)->not->toContain('Laravel', '<table');
})->with(['invitation', 'reset', 'claim', 'hot-prospect']);

it('brands every onboarding lifecycle message', function (OnboardingLifecycleStep $step): void {
    $user = User::factory()->create();
    $message = OnboardingLifecycleMessage::factory()->for($user)->create(['step' => $step]);
    $mail = (new OnboardingLifecycleNotification($message, $user, 'https://example.com/tracked-action'))->toMail($user);
    expect((string) $mail->render())->toContain('Your website, well looked after.', 'https://example.com/tracked-action');
})->with(OnboardingLifecycleStep::cases());

it('keeps client autoresponders unbranded and preserves their plain text', function (): void {
    $mail = new FormSubmissionAcknowledgement(FormSubmission::factory()->create(), 'Thank you', '<p>Thanks from Acme.</p>', 'Thanks from Acme.');
    $mail->assertSeeInHtml('<p>Thanks from Acme.</p>', false)
        ->assertDontSeeInHtml('Your website, well looked after.')
        ->assertSeeInText('Thanks from Acme.');
});

it('keeps untrusted submission values escaped inside the branded email', function (): void {
    $mail = new FormSubmissionReceived(FormSubmission::factory()->create(['data' => ['message' => '<script>alert("unsafe")</script>']]));
    $mail->assertSeeInHtml('&lt;script&gt;', false)->assertDontSeeInHtml('<script>', false);
});
