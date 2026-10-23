<?php

use App\Models\Website;
use App\Models\WebsiteLocalProject;

beforeEach(function (): void {
    $this->website = Website::factory()->create();
    $this->website->domains()->create(['domain' => 'rowglo.example', 'ownership_status' => 'verified']);
});

test('project links are explicit idempotent host specific metadata', function (): void {
    $arguments = ['domain' => 'rowglo.example', 'path' => '/Users/example/Code/rowglo', '--project-id' => 'codex-rowglo', '--host' => 'local'];
    $this->artisan('agent:link-project', $arguments)->assertSuccessful();
    $this->artisan('agent:link-project', $arguments)->assertSuccessful();
    expect(WebsiteLocalProject::query()->count())->toBe(1);
    $mapping = WebsiteLocalProject::query()->sole();
    expect($mapping->website_id)->toBe($this->website->id)->and($mapping->path)->toBe('/Users/example/Code/rowglo')->and($mapping->codex_project_id)->toBe('codex-rowglo');
    $this->artisan('agent:link-project', [...$arguments, '--host' => 'workstation'])->assertSuccessful();
    $this->artisan('agent:link-project', ['domain' => 'rowglo.example', '--remove' => true])->assertSuccessful();
    expect(WebsiteLocalProject::query()->sole()->host)->toBe('workstation');
});

test('project linking rejects unverified unknown ambiguous domains and unsafe paths', function (string $domain, string $path): void {
    Website::factory()->create()->domains()->create(['domain' => 'pending.example', 'ownership_status' => 'pending']);
    $this->artisan('agent:link-project', ['domain' => $domain, 'path' => $path])->assertFailed();
    expect(WebsiteLocalProject::query()->count())->toBe(0);
})->with([
    ['unknown.example', '/Users/example/Code/rowglo'], ['pending.example', '/Users/example/Code/rowglo'],
    ['rowglo.example', 'relative/path'], ['rowglo.example', '/Users/example/../other'], ['rowglo.example', "/tmp/bad\npath"],
]);

test('deleting a website removes its local mapping without touching any checkout', function (): void {
    WebsiteLocalProject::factory()->for($this->website)->create(['path' => '/Users/example/Code/rowglo']);
    $this->website->delete();
    expect(WebsiteLocalProject::query()->count())->toBe(0);
});
