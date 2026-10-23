<?php

use App\Models\ContentGeneration;
use App\Models\ContentOpportunity;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $this->travelTo(now()->setDate(2026, 10, 23)->setTime(7, 30));
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_site_access' => 'assigned']);
    $this->token = str_repeat('test-agent-token-', 3);
    config(['services.sitewell_agent.token_hash' => hash('sha256', $this->token), 'services.sitewell_agent.user_id' => $this->admin->id]);
    $this->website = Website::factory()->create(['name' => 'Assigned example']);
    $this->admin->assignedWebsites()->attach($this->website);
    $this->plan = ContentPlan::factory()->for($this->website)->create(['content_engine' => 'manual', 'enabled' => false, 'weekday' => 5]);
});

test('agent API fails closed for missing wrong query string and revoked tokens', function (): void {
    $this->getJson(route('agent.work'))->assertUnauthorized();
    $this->withToken('wrong')->getJson(route('agent.work'))->assertUnauthorized();
    $this->getJson(route('agent.work', ['token' => $this->token]))->assertUnauthorized();
    config(['services.sitewell_agent.token_hash' => null]);
    $this->withToken($this->token)->getJson(route('agent.work'))->assertUnauthorized();
});

test('agent API rejects customer and deleted principals even with a valid token', function (bool $deleted): void {
    if ($deleted) {
        $this->admin->delete();
    } else {
        $this->admin->update(['role' => User::ROLE_USER]);
    }
    $this->withToken($this->token)->getJson(route('agent.work'))->assertForbidden();
})->with([true, false]);

test('agent API returns scoped due work ready held planned suggestions and failures without starting anything', function (): void {
    $ready = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Ready manual work', 'manual_prompt' => 'PRIVATE PROMPT']);
    ContentRequest::factory()->for($this->website)->create(['instructions' => 'Held work', 'held_at' => now(), 'hold_reason' => 'Awaiting approval']);
    ContentRequest::factory()->for($this->website)->create(['instructions' => 'Planned work', 'planning_status' => 'planned']);
    ContentOpportunity::factory()->for($this->website)->create(['title' => 'Useful suggestion']);
    ContentGeneration::factory()->for($this->plan, 'plan')->create(['status' => 'failed', 'error' => 'Preparation failed']);
    $private = Website::factory()->create(['name' => 'UNASSIGNED WEBSITE']);
    ContentPlan::factory()->for($private)->create(['content_engine' => 'manual', 'weekday' => 5]);
    ContentRequest::factory()->for($private)->create(['instructions' => 'UNASSIGNED TASK']);
    ContentOpportunity::factory()->for($private)->create(['title' => 'UNASSIGNED SUGGESTION']);
    $response = $this->withToken($this->token)->getJson(route('agent.work'))->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('read_only', true)
        ->assertJsonPath('summary.due_today', 1)->assertJsonPath('summary.approved_tasks', 2)
        ->assertJsonPath('summary.ready_tasks', 1)->assertJsonPath('summary.planned_awaiting_approval', 1)
        ->assertJsonPath('summary.suggestions_awaiting_approval', 1)->assertJsonPath('summary.needs_attention', 1)
        ->assertJsonPath('suggestions.0.requires_approval', true)->assertJsonPath('planned.0.requires_approval', true);
    expect($response->getContent())->not->toContain('UNASSIGNED', 'PRIVATE PROMPT', $this->token);
    expect(collect($response->json('tasks'))->pluck('state')->all())->toBe(['ready', 'held'])
        ->and($ready->fresh()->picked_up_at)->toBeNull();
    $this->admin->assignedWebsites()->detach($this->website);
    $this->withToken($this->token)->getJson(route('agent.work'))->assertSuccessful()->assertJsonPath('summary.due_today', 0)->assertJsonCount(0, 'tasks');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('agent API is read only and does not accept a website override', function (): void {
    $this->withToken($this->token)->postJson(route('agent.work'))->assertMethodNotAllowed();
    $this->withToken($this->token)->getJson(route('agent.work', ['user_id' => 1, 'website_id' => 999]))
        ->assertSuccessful()->assertJsonCount(1, 'due_today');
});

test('morning check requires secure configuration and never exposes credentials on failure', function (): void {
    config(['services.sitewell_agent.endpoint' => 'http://sitewell.test/api/agent/v1/work', 'services.sitewell_agent.token' => $this->token]);
    $this->artisan('agent:check')->assertFailed();
    Http::assertNothingSent();
    config(['services.sitewell_agent.endpoint' => 'https://sitewell.example/api/agent/v1/work']);
    Http::fake(['*' => Http::response('secret error body', 401)]);
    $this->artisan('agent:check')->expectsOutputToContain('HTTP 401')->doesntExpectOutputToContain('secret error body')->assertFailed();
});

test('morning check sends credentials only to the configured endpoint and returns its work summary', function (): void {
    config(['services.sitewell_agent.endpoint' => 'https://sitewell.example/api/agent/v1/work', 'services.sitewell_agent.token' => $this->token]);
    Http::fake(['*' => Http::response(['read_only' => true, 'summary' => ['due_today' => 1]])]);
    $this->artisan('agent:check')->expectsOutputToContain('"due_today": 1')->assertSuccessful();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sitewell.example/api/agent/v1/work'
        && $request->hasHeader('Authorization', 'Bearer '.$this->token) && $request->method() === 'GET');
    Queue::assertNothingPushed();
});

test('agent API retains my reservations after an engine change and excludes other staff work', function (): void {
    $this->plan->update(['content_engine' => 'copilot']);
    ContentRequest::factory()->for($this->website)->create(['instructions' => 'My reserved task', 'manual_taken_by' => $this->admin->id, 'manual_started_at' => now(), 'picked_up_at' => now()]);
    ContentRequest::factory()->for($this->website)->create(['instructions' => 'Other staff task', 'manual_taken_by' => User::factory()->create()->id, 'manual_started_at' => now(), 'picked_up_at' => now()]);
    $this->withToken($this->token)->getJson(route('agent.work'))->assertSuccessful()->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.summary', 'My reserved task')->assertJsonPath('tasks.0.state', 'in_progress');
});

test('agent API identifies blocked manual tasks during an existing generation', function (): void {
    ContentRequest::factory()->for($this->website)->create();
    ContentGeneration::factory()->for($this->plan, 'plan')->create(['status' => 'running']);
    $this->withToken($this->token)->getJson(route('agent.work'))->assertSuccessful()
        ->assertJsonPath('tasks.0.state', 'blocked')->assertJsonPath('summary.ready_tasks', 0);
});

test('agent API bounds its task list and reports omitted work', function (): void {
    ContentRequest::factory()->count(101)->for($this->website)->create();
    $this->withToken($this->token)->getJson(route('agent.work'))->assertSuccessful()
        ->assertJsonCount(100, 'tasks')->assertJsonPath('summary.approved_tasks', 101)->assertJsonPath('truncated.tasks', true);
});

test('morning check refuses redirects instead of forwarding credentials', function (): void {
    config(['services.sitewell_agent.endpoint' => 'https://sitewell.example/api/agent/v1/work', 'services.sitewell_agent.token' => $this->token]);
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://other.example'])]);
    $this->artisan('agent:check')->expectsOutputToContain('HTTP 302')->assertFailed();
    Http::assertSentCount(1);
});
