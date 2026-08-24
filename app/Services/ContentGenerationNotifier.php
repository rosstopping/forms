<?php

namespace App\Services;

use App\Mail\ContentGenerationReady;
use App\Models\ContentGeneration;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class ContentGenerationNotifier
{
    public function ready(ContentGeneration $generation): void
    {
        $generation->refresh();
        if ($generation->notification_emailed_at || ! $generation->pull_request_url) {
            return;
        }

        $generation->loadMissing(['plan.website', 'contentRequests.searchOpportunity', 'contentRequests.seoOpportunity']);
        foreach (User::query()->where('role', User::ROLE_ADMIN)->pluck('email') as $recipient) {
            Mail::to($recipient)->send(new ContentGenerationReady($generation));
        }

        $generation->update(['notification_emailed_at' => now()]);
    }
}
