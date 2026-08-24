<?php

namespace App\Models;

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachStopReason;
use App\Enums\ProspectSequenceStep;
use Database\Factories\ProspectOutreachStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectOutreachState extends Model
{
    /** @use HasFactory<ProspectOutreachStateFactory> */
    use HasFactory;

    protected $fillable = [
        'prospect_id', 'lifecycle_state', 'engagement_score', 'temperature_override', 'automation_status',
        'sequence_step', 'follow_up_attempts', 'initial_email_sent_at', 'last_outreach_at',
        'last_engagement_at', 'video_sent_at', 'video_sent_engagement_score', 'post_video_follow_up_sent_at', 'next_action_at',
        'future_opportunity_at', 'manual_follow_up_required_at', 'manual_follow_up_reason',
        'stopped_at', 'stop_reason',
    ];

    protected $attributes = [
        'lifecycle_state' => ProspectLifecycleState::New->value,
        'engagement_score' => 0,
        'automation_status' => ProspectAutomationStatus::Active->value,
        'sequence_step' => ProspectSequenceStep::AwaitingInitialEmail->value,
        'follow_up_attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'lifecycle_state' => ProspectLifecycleState::class,
            'automation_status' => ProspectAutomationStatus::class,
            'sequence_step' => ProspectSequenceStep::class,
            'stop_reason' => ProspectOutreachStopReason::class,
            'initial_email_sent_at' => 'datetime',
            'last_outreach_at' => 'datetime',
            'last_engagement_at' => 'datetime',
            'video_sent_at' => 'datetime',
            'post_video_follow_up_sent_at' => 'datetime',
            'next_action_at' => 'datetime',
            'future_opportunity_at' => 'datetime',
            'manual_follow_up_required_at' => 'datetime',
            'manual_follow_up_reason' => 'array',
            'stopped_at' => 'datetime',
        ];
    }

    /** @return array<string, mixed> */
    public static function initialAttributesFor(Prospect $prospect): array
    {
        $lifecycleState = match (true) {
            $prospect->status === 'replied' => ProspectLifecycleState::Replied,
            $prospect->status === 'not_interested' => ProspectLifecycleState::NotInterested,
            $prospect->status === 'converted' => ProspectLifecycleState::Customer,
            $prospect->sent_at !== null && $prospect->lead_temperature === 'hot' => ProspectLifecycleState::Hot,
            $prospect->sent_at !== null && $prospect->lead_temperature === 'warm' => ProspectLifecycleState::Warm,
            $prospect->sent_at !== null => ProspectLifecycleState::InitialEmailSent,
            $prospect->scheduled_send_at !== null => ProspectLifecycleState::Scheduled,
            $prospect->approved_at !== null => ProspectLifecycleState::Approved,
            in_array($prospect->status, ['researched', 'drafted'], true) => ProspectLifecycleState::Qualified,
            default => ProspectLifecycleState::New,
        };
        $stopReason = match (true) {
            $prospect->suppressed_at !== null => ProspectOutreachStopReason::Suppressed,
            $lifecycleState === ProspectLifecycleState::Replied => ProspectOutreachStopReason::Replied,
            $lifecycleState === ProspectLifecycleState::NotInterested => ProspectOutreachStopReason::NotInterested,
            $lifecycleState === ProspectLifecycleState::Customer => ProspectOutreachStopReason::Customer,
            default => null,
        };

        return [
            'lifecycle_state' => $lifecycleState,
            'engagement_score' => match ($prospect->lead_temperature) {
                'hot' => (int) config('outreach.temperature_thresholds.hot', 10),
                'warm' => (int) config('outreach.temperature_thresholds.warm', 3),
                default => 0,
            },
            'automation_status' => $stopReason ? ProspectAutomationStatus::Stopped : ProspectAutomationStatus::Active,
            'sequence_step' => $prospect->sent_at ? ProspectSequenceStep::InitialEmail : ProspectSequenceStep::AwaitingInitialEmail,
            'initial_email_sent_at' => $prospect->sent_at,
            'last_outreach_at' => $prospect->sent_at,
            'next_action_at' => $prospect->next_follow_up_at,
            'stopped_at' => $stopReason ? ($prospect->suppressed_at ?? $prospect->replied_at ?? $prospect->converted_at ?? now()) : null,
            'stop_reason' => $stopReason,
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
