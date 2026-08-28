<?php

use App\Enums\ProspectOutreachMessageType;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use App\Services\InitialProspectOutreachGenerator;
use Illuminate\Support\Facades\Mail;

function rankedProspect(array $attributes = []): Prospect
{
    return Prospect::factory()->create(array_merge([
        'business_name' => 'Leeds Golf Club',
        'contact_name' => null,
        'status' => 'approved',
        'outreach_subject' => 'Quick question about Google',
        'outreach_body' => 'Old automated outreach.',
        'approved_at' => now(),
        'scheduled_send_at' => now()->addDay(),
        'prospecting_context' => [
            'industry' => 'Golf',
            'location' => 'Leeds',
            'search_query' => 'indoor golf leeds',
            'google_position' => 23,
        ],
    ], $attributes));
}

it('generates a short service-led subject and conversational initial email', function () {
    $draft = app(InitialProspectOutreachGenerator::class)->generate(rankedProspect());

    expect($draft)->not->toBeNull()
        ->and($draft['subject'])->toBe('indoor golf')
        ->and($draft['body'])->toContain('Leeds Golf Club')
        ->toContain('indoor golf')
        ->toContain('Leeds')
        ->toContain('Google has you quite a way down the results')
        ->toContain('web developer')
        ->toContain('send over')
        ->not->toContain('Sitewell')
        ->not->toContain('audit')
        ->not->toContain('video')
        ->not->toContain('more local enquiries');

    expect(str_word_count($draft['body']))->toBeBetween(60, 100);
});

it('renders initial outreach without audit video booking or sales extras', function () {
    $prospect = rankedProspect([
        'analysed_at' => now(),
        'showcase_video_url' => 'https://video.example.com/golf',
    ]);
    $draft = app(InitialProspectOutreachGenerator::class)->generate($prospect);
    $prospect->update(['outreach_subject' => $draft['subject'], 'outreach_body' => $draft['body']]);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create([
        'message_type' => ProspectOutreachMessageType::Initial,
        'subject' => $draft['subject'],
        'body' => $draft['body'],
    ]);

    (new ProspectOutreach($prospect, $delivery))
        ->assertSeeInHtml($draft['body'])
        ->assertDontSeeInHtml('Your website audit')
        ->assertDontSeeInHtml('Your website video')
        ->assertDontSeeInHtml('Book a call with Ross')
        ->assertDontSeeInHtml('Full disclosure');
});

it('uses natural visibility language for each ranking band', function (int $position, string $phrase) {
    $prospect = rankedProspect([
        'business_name' => 'Heating '.$position,
        'prospecting_context' => [
            'location' => 'Doncaster',
            'search_query' => 'boiler repair doncaster',
            'google_position' => $position,
        ],
    ]);
    $draft = app(InitialProspectOutreachGenerator::class)->generate($prospect);

    expect($draft['subject'])->toBe('boiler repairs')
        ->and($draft['body'])->toContain($phrase)
        ->not->toContain('position '.$position);
})->with([
    [2, 'one of the first businesses'],
    [7, 'already showing pretty well'],
    [15, 'a fair way below the top results'],
    [31, 'quite a way down the results'],
    [48, 'quite a long way down Google’s results'],
]);

it('dry runs without changing or dispatching anything', function () {
    Mail::fake();
    $prospect = rankedProspect();
    $scheduledFor = $prospect->scheduled_send_at;

    $this->artisan('outreach:regenerate-unsent-initial', ['--dry-run' => true])
        ->expectsOutputToContain('dry run')
        ->expectsOutputToContain('Would regenerate')
        ->assertSuccessful();

    expect($prospect->refresh()->outreach_subject)->toBe('Quick question about Google')
        ->and($prospect->scheduled_send_at->equalTo($scheduledFor))->toBeTrue();
    Mail::assertNothingSent();
});

it('regenerates unsent initial copy while preserving scheduling and tracking records', function () {
    Mail::fake();
    $prospect = rankedProspect();
    $scheduledFor = $prospect->scheduled_send_at;
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create([
        'message_type' => ProspectOutreachMessageType::Initial,
        'status' => 'scheduled',
        'subject' => 'Old subject',
        'body' => 'Old body',
        'scheduled_at' => $scheduledFor,
        'sent_at' => null,
    ]);
    $uuid = $delivery->uuid;

    $this->artisan('outreach:regenerate-unsent-initial')->assertSuccessful();

    $prospect->refresh();
    $delivery->refresh();
    expect($prospect->outreach_subject)->toBe('indoor golf')
        ->and($prospect->scheduled_send_at->equalTo($scheduledFor))->toBeTrue()
        ->and($prospect->approved_at)->not->toBeNull()
        ->and($delivery->uuid)->toBe($uuid)
        ->and($delivery->scheduled_at->equalTo($scheduledFor))->toBeTrue()
        ->and($delivery->subject)->toBe($prospect->outreach_subject)
        ->and($delivery->body)->toBe($prospect->outreach_body);
    Mail::assertNothingSent();

    $this->artisan('outreach:regenerate-unsent-initial')
        ->expectsOutputToContain('already uses current generated copy')
        ->assertSuccessful();

    expect(ProspectOutreachDelivery::query()->count())->toBe(1);
});

it('leaves sent initial and follow-up deliveries untouched', function () {
    Mail::fake();
    $sentProspect = rankedProspect(['sent_at' => now(), 'status' => 'contacted']);
    $sentDelivery = ProspectOutreachDelivery::factory()->for($sentProspect)->create([
        'message_type' => ProspectOutreachMessageType::Initial,
        'subject' => 'Historical subject',
        'body' => 'Historical body',
    ]);
    $pendingProspect = rankedProspect(['business_name' => 'Leeds Golf Centre']);
    $followUp = ProspectOutreachDelivery::factory()->for($pendingProspect)->create([
        'message_type' => ProspectOutreachMessageType::ColdFollowUp,
        'status' => 'scheduled',
        'subject' => 'Follow-up subject',
        'body' => 'Follow-up body',
        'sent_at' => null,
    ]);

    $this->artisan('outreach:regenerate-unsent-initial')->assertSuccessful();

    expect($sentProspect->refresh()->outreach_subject)->toBe('Quick question about Google')
        ->and($sentDelivery->refresh()->subject)->toBe('Historical subject')
        ->and($followUp->refresh()->subject)->toBe('Follow-up subject')
        ->and($followUp->body)->toBe('Follow-up body');
    Mail::assertNothingSent();
});
