<?php

namespace App\Models;

use App\Enums\OnboardingLifecycleStep;
use Database\Factories\OnboardingLifecycleMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'step', 'audience', 'scheduled_for', 'queued_at', 'sent_at', 'clicked_at', 'suppressed_at'])]
class OnboardingLifecycleMessage extends Model
{
    /** @use HasFactory<OnboardingLifecycleMessageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'step' => OnboardingLifecycleStep::class,
            'scheduled_for' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'clicked_at' => 'datetime',
            'suppressed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
