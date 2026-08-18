<?php

use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use App\Models\ProspectOutreachLink;
use App\Models\User;
use App\Notifications\ProspectBecameHot;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('adds signed open and click tracking to a live outreach email', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'outreach_subject' => 'Quick one',
        'outreach_body' => 'Hi there.',
        'website_url' => 'https://example.com',
        'analysed_at' => now(),
        'showcase_video_url' => 'https://video.example.com/prospect',
        'approved_at' => now(),
    ]);

    $this->actingAs($admin)->post(route('admin.prospects.send', $prospect))->assertRedirect();

    $delivery = $prospect->outreachDeliveries()->with('links')->sole();
    expect($delivery->sent_at)->not->toBeNull()
        ->and($delivery->recipient_email)->toBe($prospect->email)
        ->and($delivery->links->pluck('kind')->sort()->values()->all())->toBe(['book_call', 'showcase_video', 'website_audit']);

    Mail::assertSent(ProspectOutreach::class, function (ProspectOutreach $mail) use ($delivery): bool {
        $mail->assertSeeInHtml('/outreach/open/'.$delivery->uuid)
            ->assertSeeInHtml('/outreach/click/')
            ->assertSeeInHtml('View your website audit')
            ->assertSeeInHtml('signature=');

        return $mail->delivery->is($delivery);
    });
});

it('records opens and warms a cold outreach lead', function (): void {
    $prospect = Prospect::factory()->create(['lead_temperature' => 'cold']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $trackingUrl = URL::signedRoute('prospect-outreach-opens.show', $delivery);

    $this->get($trackingUrl)
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/gif');
    $this->get($trackingUrl)->assertSuccessful();

    $delivery->refresh();
    expect($delivery->first_opened_at)->not->toBeNull()
        ->and($delivery->last_opened_at)->not->toBeNull()
        ->and($delivery->open_count)->toBe(2)
        ->and($prospect->refresh()->lead_temperature)->toBe('warm')
        ->and($prospect->activities()->where('type', 'email_opened')->count())->toBe(1);
});

it('records each tracked link click and marks the lead as hot', function (): void {
    Notification::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['lead_temperature' => 'cold']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $link = ProspectOutreachLink::factory()->for($delivery, 'delivery')->create([
        'label' => 'Website video',
        'kind' => 'showcase_video',
        'destination_url' => 'https://video.example.com/prospect',
    ]);
    $trackingUrl = URL::signedRoute('prospect-outreach-links.show', $link);

    $this->get($trackingUrl)->assertRedirect('https://video.example.com/prospect');
    $this->get($trackingUrl)->assertRedirect('https://video.example.com/prospect');

    expect($link->refresh()->click_count)->toBe(2)
        ->and($link->first_clicked_at)->not->toBeNull()
        ->and($delivery->refresh()->click_count)->toBe(2)
        ->and($delivery->first_clicked_at)->not->toBeNull()
        ->and($prospect->refresh()->lead_temperature)->toBe('hot')
        ->and($prospect->activities()->where('type', 'email_clicked')->count())->toBe(1);
    Notification::assertSentTo($admin, ProspectBecameHot::class, function (ProspectBecameHot $notification) use ($admin, $prospect): bool {
        $message = $notification->toMail($admin);

        return $notification->prospect->is($prospect)
            && $notification->clickedLink === 'Website video'
            && $message->subject === 'Hot prospect: '.$prospect->business_name
            && $message->actionUrl === route('admin.prospects.show', $prospect);
    });
    Notification::assertSentToTimes($admin, ProspectBecameHot::class, 1);
});

it('does not notify administrators again when an already hot prospect clicks', function (): void {
    Notification::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['lead_temperature' => 'hot']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $link = ProspectOutreachLink::factory()->for($delivery, 'delivery')->create();

    $this->get(URL::signedRoute('prospect-outreach-links.show', $link))->assertRedirect();

    Notification::assertNothingSent();
});

it('does not cool a hot lead when another open is recorded', function (): void {
    $prospect = Prospect::factory()->create(['lead_temperature' => 'hot']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();

    $this->get(URL::signedRoute('prospect-outreach-opens.show', $delivery))->assertSuccessful();

    expect($prospect->refresh()->lead_temperature)->toBe('hot');
});

it('rejects unsigned tracking requests without recording engagement', function (): void {
    $prospect = Prospect::factory()->create(['lead_temperature' => 'cold']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $link = ProspectOutreachLink::factory()->for($delivery, 'delivery')->create();

    $this->get(route('prospect-outreach-opens.show', $delivery))->assertForbidden();
    $this->get(route('prospect-outreach-links.show', $link))->assertForbidden();

    expect($delivery->refresh()->open_count)->toBe(0)
        ->and($delivery->click_count)->toBe(0)
        ->and($link->refresh()->click_count)->toBe(0)
        ->and($prospect->refresh()->lead_temperature)->toBe('cold');
});

it('does not add tracking or create a delivery for administrator test emails', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'outreach_subject' => 'Quick one',
        'outreach_body' => 'Hi there.',
    ]);

    $this->actingAs($admin)->post(route('admin.prospects.test-email', $prospect))->assertRedirect();

    expect($prospect->outreachDeliveries()->count())->toBe(0);
    Mail::assertSent(ProspectOutreach::class, function (ProspectOutreach $mail): bool {
        $mail->assertDontSeeInHtml('/outreach/open/')
            ->assertDontSeeInHtml('/outreach/click/');

        return $mail->delivery === null;
    });
});

it('shows email engagement timing and clicked destinations on the outreach lead', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['lead_temperature' => 'hot']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create([
        'first_opened_at' => '2026-08-18 09:15:00',
        'last_opened_at' => '2026-08-18 10:30:00',
        'open_count' => 2,
        'first_clicked_at' => '2026-08-18 09:20:00',
        'last_clicked_at' => '2026-08-18 09:20:00',
        'click_count' => 1,
    ]);
    ProspectOutreachLink::factory()->for($delivery, 'delivery')->create([
        'label' => 'Website video',
        'first_clicked_at' => '2026-08-18 09:20:00',
        'last_clicked_at' => '2026-08-18 09:20:00',
        'click_count' => 1,
    ]);

    $this->actingAs($admin)->get(route('admin.prospects.show', $prospect))
        ->assertSuccessful()
        ->assertSee('Hot lead')
        ->assertSee('Email engagement')
        ->assertSee('18 Aug 2026, 09:15')
        ->assertSee('18 Aug 2026, 09:20')
        ->assertSee('Website video');
});

it('shows hot leads first and supports filtering by engagement', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Cold Prospect', 'lead_temperature' => 'cold']);
    Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Warm Prospect', 'lead_temperature' => 'warm']);
    Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Hot Prospect', 'lead_temperature' => 'hot']);

    $this->actingAs($admin)->get(route('admin.prospects.index'))
        ->assertSuccessful()
        ->assertSee('Hot leads')
        ->assertSeeInOrder(['Hot Prospect', 'Warm Prospect', 'Cold Prospect']);

    $this->get(route('admin.prospects.index', ['temperature' => 'hot']))
        ->assertSuccessful()
        ->assertSee('Hot Prospect')
        ->assertDontSee('Warm Prospect')
        ->assertDontSee('Cold Prospect');
});
