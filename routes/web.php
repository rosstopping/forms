<?php

use App\Http\Controllers\Admin\ContentPlanController;
use App\Http\Controllers\Admin\ContentRequestController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FormController;
use App\Http\Controllers\Admin\FormSubmissionController as AdminFormSubmissionController;
use App\Http\Controllers\Admin\GithubConnectionController;
use App\Http\Controllers\Admin\RemediationRunController;
use App\Http\Controllers\Admin\SearchConsoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebsiteController;
use App\Http\Controllers\Admin\WebsiteHealthReportController;
use App\Http\Controllers\Admin\WebsiteRepositoryController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\FormSubmissionSpamController;
use App\Http\Controllers\GithubWebhookController;
use App\Http\Controllers\WebsiteHealthReportController as PublicWebsiteHealthReportController;
use App\Http\Middleware\AllowFormSubmissionCors;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
});

Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('websites', WebsiteController::class);
    Route::post('websites/{website}/health-reports', [WebsiteHealthReportController::class, 'store'])->name('website-health-reports.store');
    Route::get('websites/{website}/health-reports/{websiteHealthReport}', [WebsiteHealthReportController::class, 'show'])->name('website-health-reports.show');
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
    Route::put('websites/{website}/content-plan', [ContentPlanController::class, 'update'])->name('content-plans.update');
    Route::post('websites/{website}/content-generations', [ContentPlanController::class, 'generate'])->name('content-generations.store');
    Route::post('websites/{website}/content-requests', [ContentRequestController::class, 'store'])->name('content-requests.store');
    Route::delete('websites/{website}/content-requests/{contentRequest}', [ContentRequestController::class, 'destroy'])->name('content-requests.destroy');
    Route::resource('forms', FormController::class);
    Route::resource('form-submissions', AdminFormSubmissionController::class);
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
});

Route::post('/github/webhook', GithubWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('github.webhook');
