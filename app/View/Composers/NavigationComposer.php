<?php

namespace App\View\Composers;

use App\Models\FormSubmission;
use App\Models\Website;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NavigationComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();
        $newLeadCount = 0;
        $followUpReminderCount = 0;

        $currentWebsite = request()->attributes->get('currentWebsite');

        if ($user && $currentWebsite instanceof Website && $currentWebsite->isAccessibleBy($user)) {
            $query = FormSubmission::query()
                ->whereBelongsTo($currentWebsite)
                ->where('status', 'new')
                ->where('is_spam', false);

            $newLeadCount = $query->count();

            $followUpQuery = FormSubmission::query()
                ->whereBelongsTo($currentWebsite)
                ->where('is_spam', false)
                ->whereNotIn('status', ['won', 'lost'])
                ->whereNotNull('follow_up_at')
                ->where('follow_up_at', '<=', today()->endOfDay());

            $followUpReminderCount = $followUpQuery->count();
        }

        $view->with(compact('newLeadCount', 'followUpReminderCount'));
    }
}
