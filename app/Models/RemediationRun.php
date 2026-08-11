<?php

namespace App\Models;

use Database\Factories\RemediationRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemediationRun extends Model
{
    /** @use HasFactory<RemediationRunFactory> */
    use HasFactory;

    public const STATUS_AWAITING_RUNNER = 'awaiting_runner';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PULL_REQUEST_OPEN = 'pull_request_open';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'website_health_report_id',
        'website_repository_id',
        'requested_by',
        'status',
        'copilot_task_id',
        'copilot_task_url',
        'copilot_task_state',
        'findings',
        'prompt',
        'base_sha',
        'branch',
        'commit_sha',
        'pull_request_number',
        'pull_request_url',
        'pull_request_state',
        'summary',
        'verification',
        'error',
        'started_at',
        'completed_at',
        'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'findings' => 'array',
            'verification' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'merged_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(WebsiteHealthReport::class, 'website_health_report_id');
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(WebsiteRepository::class, 'website_repository_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
