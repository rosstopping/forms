            @if ($delivery = $prospect->outreachDeliveries->first())
                <section class="ui-panel ui-section">
                    <div><h2 class="font-semibold">Email engagement</h2><p class="text-slate-500 text-base sm:text-sm">Tracking can be affected by privacy protection and email security scanners.</p></div>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-base sm:text-sm font-semibold  text-slate-500">Opened</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $delivery->first_opened_at?->setTimezone('Europe/London')->format('j M Y, H:i') ?? 'Not recorded' }}</dd>@if ($delivery->open_count)<dd class="text-base sm:text-sm text-slate-500">{{ $delivery->open_count }} {{ str('open')->plural($delivery->open_count) }} · last {{ $delivery->last_opened_at->diffForHumans() }}</dd>@endif</div>
                        <div><dt class="text-base sm:text-sm font-semibold  text-slate-500">Clicked</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $delivery->first_clicked_at?->setTimezone('Europe/London')->format('j M Y, H:i') ?? 'Not recorded' }}</dd>@if ($delivery->click_count)<dd class="text-base sm:text-sm text-slate-500">{{ $delivery->click_count }} {{ str('click')->plural($delivery->click_count) }} · last {{ $delivery->last_clicked_at->diffForHumans() }}</dd>@endif</div>
                    </dl>
                    <div class="mt-4 grid gap-2">@foreach ($delivery->links as $link)<div class="ui-well flex items-center justify-between gap-3 px-3 py-2 text-sm"><span class="font-medium text-slate-700">{{ $link->label }}</span><span class="text-base sm:text-sm text-slate-500">{{ $link->click_count ? $link->click_count.' '.str('click')->plural($link->click_count).' · '.$link->last_clicked_at->diffForHumans() : 'Not clicked' }}</span></div>@endforeach</div>
                </section>
            @endif
            <section class="ui-panel ui-section"><div><h2 class="font-semibold">Activity timeline</h2><p class="text-slate-500 text-base sm:text-sm">Lifecycle, outreach, engagement, scoring, and manual changes.</p></div><ol role="list" class="mt-5 space-y-0">@forelse ($prospect->activities as $activity)<li class="relative flex gap-3 pb-5 before:absolute before:bottom-0 before:left-[0.3125rem] before:top-3 before:w-px before:bg-slate-200 last:pb-0 last:before:hidden"><span @class(['relative z-10 mt-1 size-2.5 shrink-0 rounded-full ring-4 ring-white', 'bg-emerald-500' => in_array($activity->type, ['email_clicked', 'email_opened', 'engagement_score_changed', 'reply_detected'], true), 'bg-violet-500' => str_contains($activity->type, 'video') || str_contains($activity->type, 'manual_follow_up'), 'bg-white0' => str_contains($activity->type, 'stopped') || str_contains($activity->type, 'cancelled'), 'bg-slate-400' => ! in_array($activity->type, ['email_clicked', 'email_opened', 'engagement_score_changed', 'reply_detected'], true) && ! str_contains($activity->type, 'video') && ! str_contains($activity->type, 'manual_follow_up') && ! str_contains($activity->type, 'stopped') && ! str_contains($activity->type, 'cancelled')])></span><div class="min-w-0"><p class="font-semibold  text-slate-500 text-base sm:text-sm">{{ str($activity->type)->replace('_', ' ')->headline() }}</p><p class="mt-0.5 text-slate-800 text-base sm:text-sm">{{ $activity->description }}</p><p class="mt-1 text-slate-500 text-base sm:text-sm"><time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->setTimezone('Europe/London')->format('j M Y, H:i') }}</time> · {{ $activity->created_at->diffForHumans() }} · {{ $activity->user?->name ?: 'System' }}</p></div></li>@empty<li class="text-sm text-slate-500">No activity yet.</li>@endforelse</ol></section><section class="ui-panel ui-section">
    <h2 class="text-lg font-semibold text-balance">Email history</h2>
    <p class="mt-2 text-base text-slate-500 sm:text-sm">Saved delivery content, including scheduled messages and failures.</p>
    <ol role="list" class="mt-5 divide-y divide-slate-950/10">
        @forelse ($prospect->outreachDeliveries as $message)
            <li class="py-4 first:pt-0 last:pb-0">
                <details>
                    <summary class="cursor-pointer text-base sm:text-sm"><span class="font-medium">{{ str($message->message_type->value)->headline() }}</span> · {{ ucfirst($message->status) }}<p class="mt-1 text-slate-500">{{ $message->recipient_email }} · {{ ($message->sent_at ?? $message->scheduled_at ?? $message->created_at)->setTimezone('Europe/London')->format('j M Y, H:i') }} UK</p></summary>
                    <div class="mt-4 space-y-3 text-base sm:text-sm"><p class="font-medium">{{ $message->subject }}</p><p class="whitespace-pre-line break-words text-slate-600">{{ $message->body }}</p>@if ($message->failure_reason)<p class="text-red-700">Delivery failed: {{ $message->failure_reason }}</p>@endif</div>
                </details>
            </li>
        @empty
            <li class="text-base text-slate-500 sm:text-sm">No live emails recorded yet. Test emails appear in the activity timeline.</li>
        @endforelse
    </ol>
</section>
