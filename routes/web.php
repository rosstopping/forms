<?php

use App\Http\Controllers\Admin\BulkFormSubmissionController;
use App\Http\Controllers\Admin\BusinessProfileController;
use App\Http\Controllers\Admin\BusinessProfilePostController;
use App\Http\Controllers\Admin\BusinessProfileRecommendationController;
use App\Http\Controllers\Admin\BusinessProfileReviewController;
use App\Http\Controllers\Admin\ContentPlanController;
use App\Http\Controllers\Admin\ContentRequestController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeployPageOptimisationsController;
use App\Http\Controllers\Admin\DeployReportOptimisationsController;
use App\Http\Controllers\Admin\FormController;
use App\Http\Controllers\Admin\FormSubmissionController as AdminFormSubmissionController;
use App\Http\Controllers\Admin\GeneratePageOptimisationsController;
use App\Http\Controllers\Admin\GenerateReportOptimisationsController;
use App\Http\Controllers\Admin\GithubConnectionController;
use App\Http\Controllers\Admin\ImportProspectDiscoveryCandidatesController;
use App\Http\Controllers\Admin\OptimisationController;
use App\Http\Controllers\Admin\OptimisationDeploymentController;
use App\Http\Controllers\Admin\OptimisationVersionController;
use App\Http\Controllers\Admin\PageOptimisationRollbackController;
use App\Http\Controllers\Admin\PixelKeyController;
use App\Http\Controllers\Admin\PixelSettingsController;
use App\Http\Controllers\Admin\ProspectAnalysisController;
use App\Http\Controllers\Admin\ProspectApprovalController;
use App\Http\Controllers\Admin\ProspectController;
use App\Http\Controllers\Admin\ProspectDiscoveryController;
use App\Http\Controllers\Admin\ProspectSendController;
use App\Http\Controllers\Admin\ProspectTestEmailController;
use App\Http\Controllers\Admin\RemediationRunController;
use App\Http\Controllers\Admin\SearchConsoleController;
use App\Http\Controllers\Admin\SearchOpportunityController;
use App\Http\Controllers\Admin\SeoIntelligenceController;
use App\Http\Controllers\Admin\SeoOpportunityController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebsiteAutoresponderController;
use App\Http\Controllers\Admin\WebsiteController;
use App\Http\Controllers\Admin\WebsiteHealthReportController;
use App\Http\Controllers\Admin\WebsiteHealthReportPageController;
use App\Http\Controllers\Admin\WebsiteMemberController;
use App\Http\Controllers\Admin\WebsiteProspectController;
use App\Http\Controllers\Admin\WebsiteRepositoryController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\FormSubmissionSpamController;
use App\Http\Controllers\GithubWebhookController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\OnboardingEnquiryController;
use App\Http\Controllers\ProspectReportController;
use App\Http\Controllers\WebsiteHealthReportController as PublicWebsiteHealthReportController;
use App\Http\Middleware\AllowFormSubmissionCors;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::controller(MarketingController::class)->group(function () {
    Route::get('/', 'home')->name('marketing.home');
    Route::get('/features', 'features')->name('marketing.features');
    Route::get('/pricing', 'pricing')->name('marketing.pricing');
    Route::get('/journal', 'journal')->name('marketing.journal');
    Route::get('/journal/{slug}', 'article')->name('marketing.article');
    Route::get('/contact', 'contact')->name('marketing.contact');
});

Route::post('/contact', OnboardingEnquiryController::class)
    ->middleware('throttle:6,1')
    ->name('marketing.contact.store');

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

Route::middleware(['web', 'signed', 'throttle:20,1'])->group(function () {
    Route::get('/website-health-reports/{websiteHealthReport}', PublicWebsiteHealthReportController::class)
        ->name('website-health-reports.show');
    Route::get('/form-submissions/{formSubmission}/spam', [FormSubmissionSpamController::class, 'show'])
        ->name('form-submissions.spam.confirm');
    Route::post('/form-submissions/{formSubmission}/spam', [FormSubmissionSpamController::class, 'store'])
        ->name('form-submissions.spam.store');
    Route::get('/prospect-reports/{prospect}', ProspectReportController::class)
        ->name('prospect-reports.show');
});

Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('websites', WebsiteController::class);
    Route::put('websites/{website}/autoresponder', WebsiteAutoresponderController::class)->name('websites.autoresponder.update');
    Route::put('websites/{website}/pixel', [PixelSettingsController::class, 'update'])->name('websites.pixel.update');
    Route::post('websites/{website}/pixel/rotate-key', PixelKeyController::class)->name('websites.pixel.rotate-key');
    Route::post('websites/{website}/members', [WebsiteMemberController::class, 'store'])->name('websites.members.store');
    Route::put('websites/{website}/members/{member}', [WebsiteMemberController::class, 'update'])->name('websites.members.update');
    Route::delete('websites/{website}/members/{member}', [WebsiteMemberController::class, 'destroy'])->name('websites.members.destroy');
    Route::post('websites/{website}/health-reports', [WebsiteHealthReportController::class, 'store'])->name('website-health-reports.store');
    Route::get('websites/{website}/health-reports/{websiteHealthReport}', [WebsiteHealthReportController::class, 'show'])->name('website-health-reports.show');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/optimisations/generate', GenerateReportOptimisationsController::class)->name('report-optimisations.generate');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/optimisations/deploy-all', DeployReportOptimisationsController::class)->name('report-optimisations.deploy');
    Route::get('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}', [WebsiteHealthReportPageController::class, 'show'])->name('website-health-report-pages.show');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations/generate', GeneratePageOptimisationsController::class)->name('optimisations.generate');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations/deploy-all', DeployPageOptimisationsController::class)->name('optimisations.deploy-page');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations', [OptimisationController::class, 'store'])->name('optimisations.store');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations/{optimisation}/versions', [OptimisationVersionController::class, 'store'])->name('optimisation-versions.store');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations/{optimisation}/deploy', [OptimisationDeploymentController::class, 'deploy'])->name('optimisations.deploy');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations/{optimisation}/rollback', [OptimisationDeploymentController::class, 'rollback'])->name('optimisations.rollback');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/pages/{websiteHealthReportPage}/optimisations/rollback-all', PageOptimisationRollbackController::class)->name('optimisations.rollback-page');
    Route::post('websites/{website}/health-reports/{websiteHealthReport}/remediations', [RemediationRunController::class, 'store'])->name('remediation-runs.store');
    Route::get('websites/{website}/github/connect', [GithubConnectionController::class, 'create'])->name('github.connect');
    Route::get('github/callback', [GithubConnectionController::class, 'callback'])->name('github.callback');
    Route::get('websites/{website}/repository/create', [WebsiteRepositoryController::class, 'create'])->name('website-repositories.create');
    Route::post('websites/{website}/repository', [WebsiteRepositoryController::class, 'store'])->name('website-repositories.store');
    Route::delete('websites/{website}/repository', [WebsiteRepositoryController::class, 'destroy'])->name('website-repositories.destroy');
    Route::get('websites/{website}/search-console/connect', [SearchConsoleController::class, 'connect'])->name('search-console.connect');
    Route::get('search-console/callback', [SearchConsoleController::class, 'callback'])->name('search-console.callback');
    Route::get('websites/{website}/search-console/property', [SearchConsoleController::class, 'property'])->name('search-console.property');
    Route::post('websites/{website}/search-console/property', [SearchConsoleController::class, 'storeProperty'])->name('search-console.property.store');
    Route::get('websites/{website}/search-console/performance', [SearchConsoleController::class, 'performance'])->name('search-console.performance');
    Route::delete('websites/{website}/search-console', [SearchConsoleController::class, 'destroy'])->name('search-console.destroy');
    Route::post('websites/{website}/search-opportunities/refresh', [SearchOpportunityController::class, 'refresh'])->name('search-opportunities.refresh');
    Route::post('websites/{website}/seo-intelligence', SeoIntelligenceController::class)->name('seo-intelligence.store');
    Route::post('websites/{website}/seo-opportunities/{seoOpportunity}/queue', [SeoOpportunityController::class, 'queue'])->name('seo-opportunities.queue');
    Route::post('websites/{website}/search-opportunities/{searchOpportunity}/queue', [SearchOpportunityController::class, 'queue'])->name('search-opportunities.queue');
    Route::delete('websites/{website}/search-opportunities/{searchOpportunity}', [SearchOpportunityController::class, 'dismiss'])->name('search-opportunities.dismiss');
    Route::get('websites/{website}/business-profile/connect', [BusinessProfileController::class, 'connect'])->name('business-profile.connect');
    Route::get('business-profile/callback', [BusinessProfileController::class, 'callback'])->name('business-profile.callback');
    Route::get('websites/{website}/business-profile/locations', [BusinessProfileController::class, 'locations'])->name('business-profile.locations');
    Route::post('websites/{website}/business-profile/location', [BusinessProfileController::class, 'storeLocation'])->name('business-profile.location.store');
    Route::put('websites/{website}/business-profile', [BusinessProfileController::class, 'update'])->name('business-profile.update');
    Route::delete('websites/{website}/business-profile', [BusinessProfileController::class, 'destroy'])->name('business-profile.destroy');
    Route::post('websites/{website}/business-profile/audits', [BusinessProfileController::class, 'audit'])->name('business-profile.audits.store');
    Route::post('websites/{website}/business-profile/reviews/sync', [BusinessProfileController::class, 'syncReviews'])->name('business-profile.reviews.sync');
    Route::put('websites/{website}/business-profile/recommendations/{recommendation}', [BusinessProfileRecommendationController::class, 'update'])->name('business-profile.recommendations.update');
    Route::delete('websites/{website}/business-profile/recommendations/{recommendation}', [BusinessProfileRecommendationController::class, 'destroy'])->name('business-profile.recommendations.destroy');
    Route::post('websites/{website}/business-profile/posts', [BusinessProfilePostController::class, 'store'])->name('business-profile.posts.store');
    Route::put('websites/{website}/business-profile/posts/{post}', [BusinessProfilePostController::class, 'update'])->name('business-profile.posts.update');
    Route::post('websites/{website}/business-profile/reviews/{review}/draft', [BusinessProfileReviewController::class, 'store'])->name('business-profile.reviews.draft');
    Route::put('websites/{website}/business-profile/reviews/{review}', [BusinessProfileReviewController::class, 'update'])->name('business-profile.reviews.update');
    Route::put('websites/{website}/content-plan', [ContentPlanController::class, 'update'])->name('content-plans.update');
    Route::post('websites/{website}/content-generations', [ContentPlanController::class, 'generate'])->name('content-generations.store');
    Route::post('websites/{website}/content-requests', [ContentRequestController::class, 'store'])->name('content-requests.store');
    Route::delete('websites/{website}/content-requests/{contentRequest}', [ContentRequestController::class, 'destroy'])->name('content-requests.destroy');
    Route::resource('forms', FormController::class);
    Route::patch('form-submissions/bulk', BulkFormSubmissionController::class)->name('form-submissions.bulk');
    Route::patch('form-submissions/{form_submission}/spam', [AdminFormSubmissionController::class, 'markSpam'])->name('form-submissions.spam');
    Route::resource('form-submissions', AdminFormSubmissionController::class);
    Route::middleware(EnsureAdmin::class)->group(function (): void {
        Route::post('websites/{website}/prospect', WebsiteProspectController::class)->name('websites.prospect.store');
        Route::get('prospect-discoveries', [ProspectDiscoveryController::class, 'index'])->name('prospect-discoveries.index');
        Route::post('prospect-discoveries', [ProspectDiscoveryController::class, 'store'])->name('prospect-discoveries.store');
        Route::get('prospect-discoveries/{prospectDiscovery}', [ProspectDiscoveryController::class, 'show'])->name('prospect-discoveries.show');
        Route::post('prospect-discoveries/{prospectDiscovery}/import', ImportProspectDiscoveryCandidatesController::class)->name('prospect-discoveries.import');
        Route::post('prospects/{prospect}/analyse', ProspectAnalysisController::class)->name('prospects.analyse');
        Route::post('prospects/{prospect}/approve', ProspectApprovalController::class)->name('prospects.approve');
        Route::post('prospects/{prospect}/send', ProspectSendController::class)->name('prospects.send');
        Route::post('prospects/{prospect}/test-email', ProspectTestEmailController::class)->name('prospects.test-email');
        Route::resource('prospects', ProspectController::class);
    });
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
});

Route::post('/github/webhook', GithubWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('github.webhook');
