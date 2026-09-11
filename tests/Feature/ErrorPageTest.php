<?php

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/test-error/{status}', fn (int $status): never => abort($status));
});

it('renders branded client error pages', function (int $status, string $heading): void {
    $this->get("/test-error/{$status}")
        ->assertStatus($status)
        ->assertSee($heading)
        ->assertSeeText("Error {$status}")
        ->assertSee('Return to homepage')
        ->assertSee('Tell us what happened')
        ->assertSee('Sitewell');
})->with([
    'forbidden' => [403, 'This area is not available to you'],
    'not found' => [404, 'This page has wandered off'],
    'expired session' => [419, 'Your session has expired'],
    'rate limited' => [429, 'A short pause is needed'],
]);

it('renders branded server error pages', function (int $status, string $heading): void {
    $this->get("/test-error/{$status}")
        ->assertStatus($status)
        ->assertSee($heading)
        ->assertSeeText("Error {$status}")
        ->assertSee('check its current status before trying again.');
})->with([
    'server error' => [500, 'Something needs our attention'],
    'maintenance' => [503, 'We’ll be back shortly'],
]);

it('uses a branded fallback for other error codes', function (int $status, string $heading): void {
    $this->get("/test-error/{$status}")
        ->assertStatus($status)
        ->assertSee($heading)
        ->assertSeeText("Error {$status}");
})->with([
    'other client error' => [418, 'We could not open this page'],
    'other server error' => [502, 'Something needs our attention'],
]);
