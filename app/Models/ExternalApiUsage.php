<?php

namespace App\Models;

use Database\Factories\ExternalApiUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalApiUsage extends Model
{
    /** @use HasFactory<ExternalApiUsageFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'seo_snapshot_id', 'provider', 'endpoint', 'request_type', 'result_count', 'cost', 'provider_task_id', 'metadata', 'requested_at'];

    protected function casts(): array
    {
        return ['cost' => 'decimal:6', 'metadata' => 'array', 'requested_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SeoSnapshot::class, 'seo_snapshot_id');
    }
}
