<?php

namespace App\Models;

use Database\Factories\WebsiteBuildFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteBuild extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @use HasFactory<WebsiteBuildFactory> */
    use HasFactory;

    protected $fillable = ['requested_by', 'website_id', 'status', 'details', 'error', 'started_at', 'completed_at'];

    protected $attributes = ['status' => self::STATUS_QUEUED];

    protected function casts(): array
    {
        return ['details' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
