<?php

use App\Jobs\BuildWordPressStaticRelease;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Models\WordpressConnection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** @return array{User, Website} */
function wordpressConnectionWebsite(bool $withRepository = true): array
{
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);

    if ($withRepository) {
        WebsiteRepository::factory()->for($website)->create();
    }

    return [$owner, $website];
}

function issueWordpressPairingCode(User $owner, Website $website): string
{
    $response = test()->actingAs($owner)->post(route('admin.websites.wordpress.pairing-code', $website));

    $response->assertRedirect(route('admin.websites.show', ['website' => $website, 'tab' => 'content']));
    $code = $response->getSession()->get('wordpress_pairing_code');

    expect($code)->toBeString()->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/');

    return $code;
}

it('issues a short-lived one-time pairing code for a manageable website with a repository', function (): void {
    [$owner, $website] = wordpressConnectionWebsite();

    $code = issueWordpressPairingCode($owner, $website);
    $connection = $website->wordpressConnection()->sole();

    expect($connection->pairing_code_hash)->not->toBe($code)
        ->and($connection->pairing_code_expires_at->isAfter(now()))->toBeTrue()
        ->and($connection->credential_hash)->toBeNull();
});

it('requires a repository and management access before issuing a pairing code', function (): void {
    [$owner, $website] = wordpressConnectionWebsite(withRepository: false);
    $viewer = User::factory()->create();
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);

    $this->actingAs($owner)
        ->post(route('admin.websites.wordpress.pairing-code', $website))
        ->assertRedirect()
        ->assertSessionHas('error', 'Connect the website GitHub repository before connecting WordPress.');

    WebsiteRepository::factory()->for($website)->create();

    $this->actingAs($viewer)
        ->post(route('admin.websites.wordpress.pairing-code', $website))
        ->assertForbidden();
});

it('exchanges a valid code for a credential bound to the website hostname', function (): void {
    [$owner, $website] = wordpressConnectionWebsite();
    $code = issueWordpressPairingCode($owner, $website);

    $response = $this->postJson(route('wordpress-connections.store'), [
        'code' => Str::lower($code),
        'site_url' => 'https://www.example.com/',
        'plugin_version' => '0.1.0',
    ])->assertCreated()
        ->assertJsonPath('data.website.name', $website->name)
        ->assertJsonPath('data.website.domain', 'example.com');

    $credential = $response->json('data.credential');
    $webhookSecret = $response->json('data.webhook_secret');
    $connection = $website->wordpressConnection()->sole();

    expect($credential)->toStartWith('swp_')
        ->and($webhookSecret)->toStartWith('swh_')
        ->and($connection->credential_hash)->toBe(hash('sha256', $credential))
        ->and($connection->credential_hash)->not->toBe($credential)
        ->and($connection->webhook_secret)->toBe($webhookSecret)
        ->and($connection->getRawOriginal('webhook_secret'))->not->toContain($webhookSecret)
        ->and($connection->pairing_code_hash)->toBeNull()
        ->and($connection->wordpress_url)->toBe('https://www.example.com')
        ->and($connection->isConnected())->toBeTrue();

    Queue::assertPushed(BuildWordPressStaticRelease::class);
});

it('does not allow a code to be reused', function (): void {
    [$owner, $website] = wordpressConnectionWebsite();
    $code = issueWordpressPairingCode($owner, $website);
    $payload = [
        'code' => $code,
        'site_url' => 'https://example.com',
        'plugin_version' => '0.1.0',
    ];

    $this->postJson(route('wordpress-connections.store'), $payload)->assertCreated();
    $this->postJson(route('wordpress-connections.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

it('rejects expired codes and unrelated WordPress hostnames', function (): void {
    [$owner, $website] = wordpressConnectionWebsite();
    $code = issueWordpressPairingCode($owner, $website);

    $this->postJson(route('wordpress-connections.store'), [
        'code' => $code,
        'site_url' => 'https://attacker.example',
        'plugin_version' => '0.1.0',
    ])->assertUnprocessable()->assertJsonValidationErrors('site_url');

    $website->wordpressConnection()->update(['pairing_code_expires_at' => now()->subSecond()]);

    $this->postJson(route('wordpress-connections.store'), [
        'code' => $code,
        'site_url' => 'https://example.com',
        'plugin_version' => '0.1.0',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('authenticates heartbeats and refreshes connection metadata', function (): void {
    [$owner, $website] = wordpressConnectionWebsite();
    $code = issueWordpressPairingCode($owner, $website);
    $connected = $this->postJson(route('wordpress-connections.store'), [
        'code' => $code,
        'site_url' => 'https://example.com',
        'plugin_version' => '0.1.0',
    ])->assertCreated();
    $connectionId = $connected->json('data.connection_id');
    $credential = $connected->json('data.credential');
    $originalLastSeenAt = $website->wordpressConnection()->sole()->last_seen_at;
    $this->travel(2)->minutes();

    $this->withToken($credential)
        ->postJson(route('wordpress-connections.heartbeat', $connectionId), [
            'site_url' => 'https://example.com/',
            'plugin_version' => '0.2.0',
        ])
        ->assertNoContent();

    $connection = $website->wordpressConnection()->sole();
    expect($connection->plugin_version)->toBe('0.2.0')
        ->and($connection->last_seen_at->isAfter($originalLastSeenAt))->toBeTrue();

    $this->withToken('wrong-credential')
        ->postJson(route('wordpress-connections.heartbeat', $connectionId), [
            'site_url' => 'https://example.com',
            'plugin_version' => '0.2.0',
        ])
        ->assertUnauthorized();
});

it('allows the plugin and a website manager to revoke the credential', function (): void {
    [$owner, $website] = wordpressConnectionWebsite();
    $code = issueWordpressPairingCode($owner, $website);
    $connected = $this->postJson(route('wordpress-connections.store'), [
        'code' => $code,
        'site_url' => 'https://example.com',
        'plugin_version' => '0.1.0',
    ])->assertCreated();
    $connectionId = $connected->json('data.connection_id');
    $credential = $connected->json('data.credential');

    $this->withToken($credential)
        ->deleteJson(route('wordpress-connections.disconnect', $connectionId))
        ->assertNoContent();

    expect($website->wordpressConnection()->sole()->isConnected())->toBeFalse();

    $replacementCode = issueWordpressPairingCode($owner, $website);
    $this->postJson(route('wordpress-connections.store'), [
        'code' => $replacementCode,
        'site_url' => 'https://example.com',
        'plugin_version' => '0.1.0',
    ])->assertCreated();

    $this->actingAs($owner)
        ->delete(route('admin.websites.wordpress.connection.destroy', $website))
        ->assertRedirect();

    expect($website->wordpressConnection()->sole()->isConnected())->toBeFalse();
});

it('shows the pairing and connected states on the website content tab', function (): void {
    [$owner, $website] = wordpressConnectionWebsite();

    $this->actingAs($owner)
        ->get(route('admin.websites.show', ['website' => $website, 'tab' => 'content']))
        ->assertSuccessful()
        ->assertSee('WordPress frontend plugin')
        ->assertSee('Generate connection code');

    WordpressConnection::factory()->for($website)->create([
        'wordpress_url' => 'https://example.com',
    ]);

    $this->actingAs($owner)
        ->get(route('admin.websites.show', ['website' => $website, 'tab' => 'content']))
        ->assertSuccessful()
        ->assertSee('Connected to')
        ->assertSee('https://example.com');
});
