<?php

use App\Http\Controllers\Account\BillingController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\BulkFormSubmissionController;
use App\Http\Controllers\Admin\BulkProspectActionController;
use App\Http\Controllers\Admin\BusinessProfileController;
use App\Http\Controllers\Admin\BusinessProfilePostController;
use App\Http\Controllers\Admin\BusinessProfileRecommendationController;
use App\Http\Controllers\Admin\BusinessProfileReviewController;
use App\Http\Controllers\Admin\CompetitorController;
use App\Http\Controllers\Admin\ContentPlanController;
use App\Http\Controllers\Admin\ContentRequestController;
use App\Http\Controllers\Admin\ContentRequestPixelController;
use App\Http\Controllers\Admin\ContentSuggestionController;
use App\Http\Controllers\Admin\CurrentWebsiteController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeployPageOptimisationsController;
use App\Http\Controllers\Admin\DeployReportOptimisationsController;
use App\Http\Controllers\Admin\FormController;
use App\Http\Controllers\Admin\FormSubmissionController as AdminFormSubmissionController;
use App\Http\Controllers\Admin\GeneratePageOptimisationsController;
use App\Http\Controllers\Admin\GenerateReportOptimisationsController;
use App\Http\Controllers\Admin\GithubConnectionController;
use App\Http\Controllers\Admin\ImportProspectDiscoveryCandidatesController;
use App\Http\Controllers\Admin\ImportSeoProspectCandidatesController;
use App\Http\Controllers\Admin\ManagedPostmarkConnectionController;
use App\Http\Controllers\Admin\ManagedPostmarkVerificationController;
use App\Http\Controllers\Admin\OnboardingCallController;
use App\Http\Controllers\Admin\OnboardingLeadController;
use App\Http\Controllers\Admin\OptimisationController;
use App\Http\Controllers\Admin\OptimisationDeploymentController;
use App\Http\Controllers\Admin\OptimisationVersionController;
use App\Http\Controllers\Admin\PageOptimisationRollbackController;
use App\Http\Controllers\Admin\PixelKeyController;
use App\Http\Controllers\Admin\PixelSettingsController;
use App\Http\Controllers\Admin\PostmarkConnectionTestController;
use App\Http\Controllers\Admin\ProspectAnalysisController;
use App\Http\Controllers\Admin\ProspectApprovalController;
use App\Http\Controllers\Admin\ProspectController;
use App\Http\Controllers\Admin\ProspectDiscoveryController;
use App\Http\Controllers\Admin\ProspectingIndustryProfileController;
use App\Http\Controllers\Admin\ProspectingLocationController;
use App\Http\Controllers\Admin\ProspectLifecycleActionController;
use App\Http\Controllers\Admin\ProspectPersonalisedVideoController;
use App\Http\Controllers\Admin\ProspectScheduleController;
use App\Http\Controllers\Admin\ProspectSendController;
use App\Http\Controllers\Admin\ProspectTestEmailController;
use App\Http\Controllers\Admin\RemediationRunController;
use App\Http\Controllers\Admin\ReportRemediationController;
use App\Http\Controllers\Admin\RerunSeoProspectSearchController;
use App\Http\Controllers\Admin\RunAutomatedProspectDiscoveryController;
use App\Http\Controllers\Admin\SearchConsoleController;
use App\Http\Controllers\Admin\SearchOpportunityController;
use App\Http\Controllers\Admin\SeoIntelligenceController;
use App\Http\Controllers\Admin\SeoKeywordController;
use App\Http\Controllers\Admin\SeoOpportunityController;
use App\Http\Controllers\Admin\SeoProspectSearchController;
use App\Http\Controllers\Admin\SeoSnapshotSettingsController;
use App\Http\Controllers\Admin\SeoTargetKeywordController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserImpersonationController;
use App\Http\Controllers\Admin\UserOnboardingCallController;
use App\Http\Controllers\Admin\WebsiteAiChatController;
use App\Http\Controllers\Admin\WebsiteAiQuestionCreditController;
use App\Http\Controllers\Admin\WebsiteAiQuestionReportController;
use App\Http\Controllers\Admin\WebsiteAiQuestionStatusController;
use App\Http\Controllers\Admin\WebsiteAutoresponderController;
use App\Http\Controllers\Admin\WebsiteBuilderController;
use App\Http\Controllers\Admin\WebsiteController;
use App\Http\Controllers\Admin\WebsiteHealthReportController;
use App\Http\Controllers\Admin\WebsiteHealthReportPageController;
use App\Http\Controllers\Admin\WebsiteMemberController;
use App\Http\Controllers\Admin\WebsiteProspectController;
use App\Http\Controllers\Admin\WebsiteRepositoryController;
use App\Http\Controllers\Admin\WordPressConnectionController;
use App\Http\Controllers\Admin\WordPressPairingCodeController;
use App\Http\Controllers\Admin\WordPressStaticReleaseController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\WebsiteInvitationController;
use App\Http\Controllers\CalWebhookController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\FormSubmissionSpamController;
use App\Http\Controllers\FreeSiteAuditController;
use App\Http\Controllers\GithubWebhookController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\OnboardingEnquiryController;
use App\Http\Controllers\OnboardingLifecycleClickController;
use App\Http\Controllers\ProspectOutreachClickController;
use App\Http\Controllers\ProspectOutreachOpenController;
use App\Http\Controllers\ProspectReportController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WebsiteAuditOnboardingController;
use App\Http\Controllers\WebsiteHealthReportController as PublicWebsiteHealthReportController;
use App\Http\Middleware\AllowFormSubmissionCors;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\ResolveCurrentWebsite;
use App\Support\WebsiteNavigation;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::controller(MarketingController::class)->group(function () {
    Route::get('/', 'home')->name('marketing.home');
    Route::get('/how-it-works', 'howItWorks')->name('marketing.how-it-works');
    Route::get('/features', 'features')->name('marketing.features');
    Route::get('/features/{feature}', 'feature')->name('marketing.feature');
    Route::get('/pricing', 'pricing')->name('marketing.pricing');
    Route::get('/examples', 'examples')->name('marketing.examples');
    Route::get('/compare', 'comparison')->name('marketing.comparison');
    Route::get('/about', 'about')->name('marketing.about');
    Route::get('/faqs', 'faqs')->name('marketing.faqs');
    Route::get('/for/{industry}', 'industry')->name('marketing.industry');
    Route::get('/journal', 'journal')->name('marketing.journal');
    Route::get('/journal/{slug}', 'article')->name('marketing.article');
    Route::get('/contact', 'contact')->name('marketing.contact');
    Route::get('/wordpress', 'wordpress')->name('marketing.wordpress');
    Route::get('/wordpress/download', 'downloadWordPressPlugin')->name('marketing.wordpress.download');
    Route::get('/privacy-policy', 'privacy')->name('marketing.privacy');
    Route::get('/terms-of-service', 'terms')->name('marketing.terms');
    Route::get('/sitemap.xml', 'sitemap')->name('marketing.sitemap');
    Route::get('/{landing}', 'landing')
        ->whereIn('landing', array_keys(config('marketing.landing_pages', [])))
        ->name('marketing.landing');
});

Route::get('/outreach/open/{delivery}', ProspectOutreachOpenController::class)
    ->middleware(['signed', 'throttle:120,1'])
    ->name('prospect-outreach-opens.show');
Route::get('/outreach/click/{link}', ProspectOutreachClickController::class)
    ->middleware(['signed', 'throttle:120,1'])
    ->name('prospect-outreach-links.show');

Route::post('/contact', OnboardingEnquiryController::class)
    ->middleware('throttle:6,1')
    ->name('marketing.contact.store');

Route::post('/cal/webhook', CalWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('cal.webhook');

Route::get('/onboarding/messages/{onboardingLifecycleMessage}/continue', OnboardingLifecycleClickController::class)
    ->middleware(['signed', 'throttle:60,1'])
    ->name('onboarding-lifecycle.click');

Route::redirect('/free-site-audit', '/get-started', 301);
Route::get('/get-started', [FreeSiteAuditController::class, 'create'])->name('marketing.free-site-audit');
Route::post('/get-started', [FreeSiteAuditController::class, 'store'])
    ->middleware('throttle:website-audits')
    ->name('marketing.free-site-audit.store');
Route::get('/website-audits/{websiteAudit}', [FreeSiteAuditController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('marketing.website-audits.show');
Route::get('/website-audits/{websiteAudit}/status', [FreeSiteAuditController::class, 'status'])
    ->middleware('throttle:120,1')
    ->name('marketing.website-audits.status');
Route::post('/website-audits/{websiteAudit}/continue', [WebsiteAuditOnboardingController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('marketing.website-audits.claim');

Route::middleware(['signed', 'throttle:20,1'])->group(function () {
    Route::get('/website-audits/{websiteAudit}/onboarding', [WebsiteAuditOnboardingController::class, 'edit'])
        ->name('marketing.website-audits.onboarding');
    Route::post('/website-audits/{websiteAudit}/onboarding', [WebsiteAuditOnboardingController::class, 'update'])
        ->name('marketing.website-audits.onboarding.complete');
});

Route::get('/submitted', function (Request $request) {
    $returnUrl = $request->header('referer') ?: url('/');

    return view('submitted', ['returnUrl' => $returnUrl]);
})->name('forms.submitted');

Route::middleware(['web', 'throttle:120,1'])->group(function () {
    Route::options('/submit', fn () => response()->noContent())
        ->middleware(AllowFormSubmissionCors::class);

    Route::post('/submit', [FormSubmissionController::class, 'store'])
        ->name('forms.submit')
        ->middleware(AllowFormSubmissionCors::class)
        ->withoutMiddleware([PreventRequestForgery::class]);
});

Route::middleware('web')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['web', 'guest'])->group(function () {
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware(['web', 'signed', 'throttle:20,1'])->group(function () {
    Route::get('/website-invitations/{user}', [WebsiteInvitationController::class, 'edit'])->name('website-invitations.accept');
    Route::put('/website-invitations/{user}', [WebsiteInvitationController::class, 'update'])->name('website-invitations.update');
});

Route::middleware(['web', 'signed', 'throttle:20,1'])->group(function () {
    Route::get('/website-health-reports/{websiteHealthReport}', PublicWebsiteHealthReportController::class)
        ->name('website-health-reports.show');
    Route::get('/admin/websites/{website}/content-suggestions/queue', ContentSuggestionController::class)
        ->name('admin.content-suggestions.store');
    Route::get('/form-submissions/{formSubmission}/spam', [FormSubmissionSpamController::class, 'show'])
        ->name('form-submissions.spam.confirm');
    Route::post('/form-submissions/{formSubmission}/spam', [FormSubmissionSpamController::class, 'store'])
        ->name('form-submissions.spam.store');
    Route::get('/prospect-reports/{prospect}', ProspectReportController::class)
        ->name('prospect-reports.show');
});

Route::middleware(['web', 'auth', ResolveCurrentWebsite::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('current-website', CurrentWebsiteController::class)->name('current-website.update');
    Route::get('account/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('account/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('account/profile', [ProfileController::class, 'update'])->middleware('impersonate.protect')->name('profile.update');
    Route::post('account/billing/checkout', [BillingController::class, 'checkout'])->middleware(['impersonate.protect', 'throttle:10,1'])->name('billing.checkout');
    Route::post('account/billing/portal', [BillingController::class, 'portal'])->middleware(['impersonate.protect', 'throttle:10,1'])->name('billing.portal');
    Route::resource('websites', WebsiteController::class);
    Route::get('websites/{website}/section/{section}', [WebsiteController::class, 'show'])
        ->whereIn('section', WebsiteNavigation::SECTIONS)
        ->name('websites.section');
    Route::post('websites/{website}/wordpress/pairing-code', WordPressPairingCodeController::class)->middleware('throttle:6,1')->name('websites.wordpress.pairing-code');
    Route::delete('websites/{website}/wordpress/connection', WordPressConnectionController::class)->middleware('throttle:6,1')->name('websites.wordpress.connection.destroy');
    Route::post('websites/{website}/wordpress/releases', WordPressStaticReleaseController::class)->middleware('throttle:6,1')->name('websites.wordpress.releases.store');
    Route::get('website-builder', [WebsiteBuilderController::class, 'create'])->name('website-builder.create');
    Route::post('website-builder', [WebsiteBuilderController::class, 'store'])->name('website-builder.store');
    Route::get('website-builder/github/connect', [GithubConnectionController::class, 'authorizeBuilder'])->name('website-builder.github.connect');
    Route::put('websites/{website}/autoresponder', WebsiteAutoresponderController::class)->name('websites.autoresponder.update');
    Route::post('websites/{website}/mail/managed', ManagedPostmarkConnectionController::class)->middleware('throttle:5,1')->name('websites.mail.managed.store');
    Route::post('websites/{website}/mail/managed/verify', ManagedPostmarkVerificationController::class)->middleware('throttle:10,1')->name('websites.mail.managed.verify');
    Route::post('websites/{website}/mail/test', PostmarkConnectionTestController::class)->middleware('throttle:5,1')->name('websites.mail.test');
    Route::put('websites/{website}/pixel', [PixelSettingsController::class, 'update'])->middleware('membership:growth')->name('websites.pixel.update');
    Route::post('websites/{website}/pixel/rotate-key', PixelKeyController::class)->middleware('membership:growth')->name('websites.pixel.rotate-key');
    Route::post('websites/{website}/members', [WebsiteMemberController::class, 'store'])->middleware('membership:growth')->name('websites.members.store');
    Route::post('websites/{website}/assistant/questions', WebsiteAiChatController::class)->middleware(['membership:complete', 'throttle:10,1'])->name('websites.assistant.questions.store');
    Route::get('websites/{website}/assistant/questions/{websiteAiQuestion}', WebsiteAiQuestionStatusController::class)->middleware(['membership:complete', 'throttle:120,1'])->name('websites.assistant.questions.show');
    Route::post('websites/{website}/assistant/questions/{websiteAiQuestion}/report', [WebsiteAiQuestionReportController::class, 'store'])->middleware('throttle:10,1')->name('websites.assistant.questions.report');
    Route::put('websites/{website}/members/{member}', [WebsiteMemberController::class, 'update'])->name('websites.members.update');
    Route::delete('websites/{website}/members/{member}', [WebsiteMemberController::class, 'destroy'])->name('websites.members.destroy');
    Route::post('websites/{website}/health-reports', [WebsiteHealthReportController::class, 'store'])->middleware('membership:health_reports')->name('website-health-reports.store');
    Route::get('websites/{website}/health-reports/{websiteHealthReport}', [WebsiteHealthReportController::class, 'show'])->name('website-health-reports.show');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/optimisations/generate', GenerateReportOptimisationsController::class)->middleware('membership:growth')->name('report-optimisations.generate');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/remediate', ReportRemediationController::class)->middleware('membership:growth')->name('report-remediation.store');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/optimisations/deploy-all', DeployReportOptimisationsController::class)->middleware('membership:growth')->name('report-optimisations.deploy');
    Route::get('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}', [WebsiteHealthReportPageController::class, 'show'])->name('website-health-report-pages.show');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations/generate', GeneratePageOptimisationsController::class)->middleware('membership:growth')->name('optimisations.generate');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations/deploy-all', DeployPageOptimisationsController::class)->middleware('membership:growth')->name('optimisations.deploy-page');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations', [OptimisationController::class, 'store'])->middleware('membership:growth')->name('optimisations.store');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations/{optimisation}/versions', [OptimisationVersionController::class, 'store'])->middleware('membership:growth')->name('optimisation-versions.store');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations/{optimisation}/deploy', [OptimisationDeploymentController::class, 'deploy'])->middleware('membership:growth')->name('optimisations.deploy');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations/{optimisation}/rollback', [OptimisationDeploymentController::class, 'rollback'])->middleware('membership:growth')->name('optimisations.rollback');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations/rollback-all', PageOptimisationRollbackController::class)->middleware('membership:growth')->name('optimisations.rollback-page');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/remediations', [RemediationRunController::class, 'store'])->name('remediation-runs.store');
    Route::post('websites/{website}/pixel/content-requests', ContentRequestPixelController::class)->middleware('membership:growth')->name('websites.pixel.content-requests.store');
    Route::get('websites/{website}/github/connect', [GithubConnectionController::class, 'create'])->name('github.connect');
    Route::get('github/callback', [GithubConnectionController::class, 'callback'])->name('github.callback');
    Route::get('websites/{website}/repository/create', [WebsiteRepositoryController::class, 'create'])->name('website-repositories.create');
    Route::post('websites/{website}/repository', [WebsiteRepositoryController::class, 'store'])->name('website-repositories.store');
    Route::delete('websites/{website}/repository', [WebsiteRepositoryController::class, 'destroy'])->name('website-repositories.destroy');
    Route::get('websites/{website}/search-console/connect', [SearchConsoleController::class, 'connect'])->middleware('membership:search_console')->name('search-console.connect');
    Route::get('search-console/callback', [SearchConsoleController::class, 'callback'])->name('search-console.callback');
    Route::get('websites/{website}/search-console/property', [SearchConsoleController::class, 'property'])->middleware('membership:search_console')->name('search-console.property');
    Route::post('websites/{website}/search-console/property', [SearchConsoleController::class, 'storeProperty'])->middleware('membership:search_console')->name('search-console.property.store');
    Route::get('websites/{website}/search-console/performance', [SearchConsoleController::class, 'performance'])->middleware('membership:search_console')->name('search-console.performance');
    Route::get('websites/{website}/search-console/performance/query', [SearchConsoleController::class, 'query'])->middleware('membership:search_console')->name('search-console.queries.show');
    Route::delete('websites/{website}/search-console', [SearchConsoleController::class, 'destroy'])->middleware('membership:search_console')->name('search-console.destroy');
    Route::post('websites/{website}/search-opportunities/refresh', [SearchOpportunityController::class, 'refresh'])->middleware('membership:growth')->name('search-opportunities.refresh');
    Route::middleware('membership:growth')->controller(CompetitorController::class)->group(function (): void {
        Route::post('websites/{website}/competitors', 'store')->name('competitors.store');
        Route::put('websites/{website}/competitors/{competitor}', 'update')->name('competitors.update');
        Route::post('websites/{website}/competitors/{competitor}/audit', 'audit')->middleware('throttle:10,1')->name('competitors.audit');
        Route::get('websites/{website}/competitor-audits/{audit}', 'show')->name('competitor-audits.show');
        Route::post('websites/{website}/competitor-opportunities/{opportunity}/queue', 'queue')->name('competitor-opportunities.queue');
    });
    Route::post('websites/{website}/seo-intelligence', SeoIntelligenceController::class)->middleware('membership:growth')->name('seo-intelligence.store');
    Route::get('websites/{website}/seo-keywords/{seoKeyword}', [SeoKeywordController::class, 'show'])->middleware('membership:growth')->name('seo-keywords.show');
    Route::put('websites/{website}/seo-snapshot-settings', SeoSnapshotSettingsController::class)->middleware('membership:growth')->name('seo-snapshot-settings.update');
    Route::middleware('membership:growth')->controller(SeoTargetKeywordController::class)->group(function (): void {
        Route::post('websites/{website}/seo-target-keywords', 'store')->name('seo-target-keywords.store');
        Route::post('websites/{website}/seo-target-keywords/bulk', 'bulkStore')->name('seo-target-keywords.bulk-store');
        Route::put('websites/{website}/seo-target-keywords/{seoTargetKeyword}', 'update')->name('seo-target-keywords.update');
        Route::delete('websites/{website}/seo-target-keywords/{seoTargetKeyword}', 'archive')->name('seo-target-keywords.archive');
        Route::post('websites/{website}/seo-target-keywords/{seoTargetKeyword}/restore', 'restore')->name('seo-target-keywords.restore');
        Route::post('websites/{website}/seo-target-keywords/{seoTargetKeyword}/check', 'check')->middleware('throttle:20,1')->name('seo-target-keywords.check');
    });
    Route::post('websites/{website}/seo-opportunities/{seoOpportunity}/queue', [SeoOpportunityController::class, 'queue'])->middleware('membership:growth')->name('seo-opportunities.queue');
    Route::post('websites/{website}/search-opportunities/{searchOpportunity}/queue', [SearchOpportunityController::class, 'queue'])->middleware('membership:growth')->name('search-opportunities.queue');
    Route::delete('websites/{website}/search-opportunities/{searchOpportunity}', [SearchOpportunityController::class, 'dismiss'])->middleware('membership:growth')->name('search-opportunities.dismiss');
    Route::get('websites/{website}/business-profile/connect', [BusinessProfileController::class, 'connect'])->middleware('membership:complete')->name('business-profile.connect');
    Route::get('business-profile/callback', [BusinessProfileController::class, 'callback'])->name('business-profile.callback');
    Route::get('websites/{website}/business-profile/locations', [BusinessProfileController::class, 'locations'])->middleware('membership:complete')->name('business-profile.locations');
    Route::post('websites/{website}/business-profile/location', [BusinessProfileController::class, 'storeLocation'])->middleware('membership:complete')->name('business-profile.location.store');
    Route::put('websites/{website}/business-profile', [BusinessProfileController::class, 'update'])->middleware('membership:complete')->name('business-profile.update');
    Route::delete('websites/{website}/business-profile', [BusinessProfileController::class, 'destroy'])->middleware('membership:complete')->name('business-profile.destroy');
    Route::post('websites/{website}/business-profile/audits', [BusinessProfileController::class, 'audit'])->middleware('membership:complete')->name('business-profile.audits.store');
    Route::post('websites/{website}/business-profile/reviews/sync', [BusinessProfileController::class, 'syncReviews'])->middleware('membership:complete')->name('business-profile.reviews.sync');
    Route::put('websites/{website}/business-profile/recommendations/{recommendation}', [BusinessProfileRecommendationController::class, 'update'])->middleware('membership:complete')->name('business-profile.recommendations.update');
    Route::delete('websites/{website}/business-profile/recommendations/{recommendation}', [BusinessProfileRecommendationController::class, 'destroy'])->middleware('membership:complete')->name('business-profile.recommendations.destroy');
    Route::post('websites/{website}/business-profile/posts', [BusinessProfilePostController::class, 'store'])->middleware('membership:complete')->name('business-profile.posts.store');
    Route::put('websites/{website}/business-profile/posts/{post}', [BusinessProfilePostController::class, 'update'])->middleware('membership:complete')->name('business-profile.posts.update');
    Route::post('websites/{website}/business-profile/reviews/{review}/draft', [BusinessProfileReviewController::class, 'store'])->middleware('membership:complete')->name('business-profile.reviews.draft');
    Route::put('websites/{website}/business-profile/reviews/{review}', [BusinessProfileReviewController::class, 'update'])->middleware('membership:complete')->name('business-profile.reviews.update');
    Route::put('websites/{website}/content-plan', [ContentPlanController::class, 'update'])->middleware('membership:growth')->name('content-plans.update');
    Route::post('websites/{website}/content-generations', [ContentPlanController::class, 'generate'])->middleware('membership:growth')->name('content-generations.store');
    Route::post('websites/{website}/content-generations/{contentGeneration}/sync', [ContentPlanController::class, 'syncGeneration'])->middleware('membership:growth')->name('content-generations.sync');
    Route::delete('websites/{website}/content-generations/{contentGeneration}', [ContentPlanController::class, 'cancelGeneration'])->middleware('membership:growth')->name('content-generations.destroy');
    Route::post('websites/{website}/content-requests', [ContentRequestController::class, 'store'])->middleware('membership:growth')->name('content-requests.store');
    Route::delete('websites/{website}/content-requests/{contentRequest}', [ContentRequestController::class, 'destroy'])->middleware('membership:growth')->name('content-requests.destroy');
    Route::resource('forms', FormController::class);
    Route::patch('form-submissions/bulk', BulkFormSubmissionController::class)->name('form-submissions.bulk');
    Route::post('form-submissions/{formSubmission}/resend-notification', [AdminFormSubmissionController::class, 'resendNotification'])->middleware('throttle:5,1')->name('form-submissions.resend-notification');
    Route::patch('form-submissions/{form_submission}/spam', [AdminFormSubmissionController::class, 'markSpam'])->name('form-submissions.spam');
    Route::resource('form-submissions', AdminFormSubmissionController::class);
    Route::get('onboarding/call', OnboardingCallController::class)->middleware('throttle:30,1')->name('onboarding-call');
    Route::middleware(EnsureAdmin::class)->group(function (): void {
        Route::get('onboarding', OnboardingLeadController::class)->name('onboarding.index');
        Route::patch('users/{user}/onboarding-call', UserOnboardingCallController::class)->name('users.onboarding-call.update');
        Route::get('assistant/reports/{websiteAiQuestion}', [WebsiteAiQuestionReportController::class, 'show'])->name('website-ai-question-reports.show');
        Route::post('assistant/reports/{websiteAiQuestion}/credit', WebsiteAiQuestionCreditController::class)->name('website-ai-question-reports.credit');
        Route::post('websites/{website}/prospect', WebsiteProspectController::class)->name('websites.prospect.store');
        Route::get('prospect-discoveries', [ProspectDiscoveryController::class, 'index'])->name('prospect-discoveries.index');
        Route::post('prospect-discoveries', [ProspectDiscoveryController::class, 'store'])->name('prospect-discoveries.store');
        Route::get('prospect-discoveries/{prospectDiscovery}', [ProspectDiscoveryController::class, 'show'])->name('prospect-discoveries.show');
        Route::post('prospect-discoveries/{prospectDiscovery}/import', ImportProspectDiscoveryCandidatesController::class)->name('prospect-discoveries.import');
        Route::post('prospect-discoveries/seo-opportunities', [SeoProspectSearchController::class, 'store'])->name('seo-prospect-searches.store');
        Route::get('prospect-discoveries/seo-opportunities/{seoProspectSearch}', [SeoProspectSearchController::class, 'show'])->name('seo-prospect-searches.show');
        Route::post('prospect-discoveries/seo-opportunities/{seoProspectSearch}/import', ImportSeoProspectCandidatesController::class)->name('seo-prospect-searches.import');
        Route::post('prospect-discoveries/seo-opportunities/{seoProspectSearch}/rerun', RerunSeoProspectSearchController::class)->name('seo-prospect-searches.rerun');
        Route::post('prospect-discoveries/automatic', RunAutomatedProspectDiscoveryController::class)->name('prospect-discoveries.automatic');
        Route::resource('prospecting-industry-profiles', ProspectingIndustryProfileController::class)->except(['show', 'destroy']);
        Route::resource('prospecting-locations', ProspectingLocationController::class)->only(['create', 'store', 'edit', 'update']);
        Route::post('prospects/{prospect}/analyse', ProspectAnalysisController::class)->name('prospects.analyse');
        Route::post('prospects/{prospect}/approve', ProspectApprovalController::class)->name('prospects.approve');
        Route::post('prospects/{prospect}/schedule', ProspectScheduleController::class)->name('prospects.schedule');
        Route::post('prospects/{prospect}/send', ProspectSendController::class)->name('prospects.send');
        Route::post('prospects/{prospect}/personalised-video', ProspectPersonalisedVideoController::class)->name('prospects.personalised-video');
        Route::patch('prospects/{prospect}/lifecycle', ProspectLifecycleActionController::class)->name('prospects.lifecycle');
        Route::post('prospects/{prospect}/test-email', ProspectTestEmailController::class)->name('prospects.test-email');
        Route::post('prospects/bulk', BulkProspectActionController::class)->name('prospects.bulk');
        Route::resource('prospects', ProspectController::class);
    });
    Route::post('users/{user}/impersonate', [UserImpersonationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('users.impersonate.store');
    Route::delete('impersonation', [UserImpersonationController::class, 'destroy'])
        ->middleware('throttle:10,1')
        ->name('impersonation.destroy');
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
});

Route::post('/stripe/webhook', StripeWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('stripe.webhook');

Route::post('/github/webhook', GithubWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('github.webhook');
