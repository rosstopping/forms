<?php

namespace App\Models;

use Database\Factories\ContentGenerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentGeneration extends Model
{
    /** @use HasFactory<ContentGenerationFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PULL_REQUEST_OPEN = 'pull_request_open';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['content_plan_id', 'website_repository_id', 'requested_by', 'scheduled_for', 'status', 'search_performance', 'prompt', 'copilot_task_id', 'copilot_task_url', 'copilot_task_state', 'pull_request_number', 'pull_request_url', 'pull_request_state', 'error', 'started_at', 'completed_at', 'merged_at'];

    protected $attributes = ['status' => self::STATUS_PENDING];

    protected function casts(): array
    {
        return ['scheduled_for' => 'date', 'search_performance' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'merged_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContentPlan::class, 'content_plan_id');
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
