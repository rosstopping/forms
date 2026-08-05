<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FormController;
use App\Http\Controllers\Admin\FormSubmissionController as AdminFormSubmissionController;
use App\Http\Controllers\Admin\WebsiteController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\FormSubmissionController;
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

Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('websites', WebsiteController::class);
    Route::resource('forms', FormController::class);
    Route::resource('form-submissions', AdminFormSubmissionController::class);
});
