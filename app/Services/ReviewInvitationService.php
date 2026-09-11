<?php

namespace App\Services;

use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Support\Str;

class ReviewInvitationService
{
    public function unavailableReason(FormSubmission $lead, ?User $requester): ?string
    {
        $website = $lead->website;
        $recipient = $lead->replyToEmail();

        return match (true) {
            ! $website || ! $website->isManageableBy($requester) => 'Only a website manager can send a review invitation.',
            ! $website->is_active => 'Review invitations are paused while this website is inactive.',
            ! $requester->isAdmin() && ! $website->owner?->hasActiveMembership() => 'An active website subscription is required to send a review invitation.',
            $lead->is_spam => 'Spam leads cannot receive review invitations.',
            $lead->status !== 'work_completed' => 'Mark this lead as Work completed before requesting a review.',
            ! $recipient => 'This lead needs a valid customer email address.',
            app(WebsiteMailRecipients::class)->isViewer($website, $recipient) => 'Website viewers cannot receive review invitation emails.',
            ! $website->review_url => 'Save a review link for this website first.',
            default => null,
        };
    }

    /** @return array{recipient: string, subject: string, from_email: string, from_name: string, body: string, review_url: string} */
    public function snapshot(FormSubmission $lead): array
    {
        $business = Str::limit(Str::squish($lead->website->name), 150, '');
        $name = Str::limit(Str::squish($lead->contactName() ?? ''), 80, '');
        $greeting = $name !== '' ? 'Hello '.$name.',' : 'Hello,';

        return [
            'recipient' => $lead->replyToEmail() ?? '',
            'subject' => 'Share your experience with '.$business,
            'from_email' => (string) config('forms.autoresponder_from_address', 'mail@digizu.co.uk'),
            'from_name' => $business,
            'body' => $greeting."\n\nThank you for choosing ".$business.". Now that our work is complete, we would appreciate an honest review of your experience.\n\nYour feedback helps other customers and helps us improve.\n\nThank you,\n".$business,
            'review_url' => $lead->website->review_url ?? '',
        ];
    }

    /** @param array<string, string> $snapshot */
    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
