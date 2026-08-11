<?php

namespace App\View\Composers;

use App\Models\FormSubmission;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NavigationComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();
        $newLeadCount = 0;
        $followUpReminderCount = 0;

        if ($user) {
            $query = FormSubmission::query()
                ->where('status', 'new')
                ->where('is_spam', false);

            if (! $user->isAdmin()) {
                $query->whereHas('website', fn ($query) => $query->accessibleTo($user));
            }

            $newLeadCount = $query->count();

            $followUpQuery = FormSubmission::query()
                ->where('is_spam', false)
                ->whereNotIn('status', ['won', 'lost'])
                ->whereNotNull('follow_up_at')
                ->where('follow_up_at', '<=', today()->endOfDay());

            if (! $user->isAdmin()) {
                $followUpQuery->whereHas('website', fn ($query) => $query->accessibleTo($user));
            }

            $followUpReminderCount = $followUpQuery->count();
        }

        $view->with(compact('newLeadCount', 'followUpReminderCount'));
    }
}
