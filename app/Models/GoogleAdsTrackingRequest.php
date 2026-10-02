<?php

namespace App\Models;

use Database\Factories\GoogleAdsTrackingRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAdsTrackingRequest extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PULL_REQUEST_OPEN = 'pull_request_open';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNCERTAIN = 'uncertain';

    /** @use HasFactory<GoogleAdsTrackingRequestFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'website_repository_id', 'requested_by', 'customer_id', 'conversion_action_id', 'conversion_action_name', 'send_to', 'lead_success_description', 'status', 'copilot_task_id', 'copilot_task_url', 'copilot_task_state', 'pull_request_number', 'pull_request_url', 'error', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
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
