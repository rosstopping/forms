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

    protected $fillable = ['backlink_audit_id', 'competitor_audit_id', 'website_id', 'seo_snapshot_id', 'seo_target_keyword_id', 'provider', 'endpoint', 'request_type', 'result_count', 'cost', 'provider_task_id', 'metadata', 'requested_at'];

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

    public function targetKeyword(): BelongsTo
    {
        return $this->belongsTo(SeoTargetKeyword::class, 'seo_target_keyword_id');
    }

    public function backlinkAudit(): BelongsTo
    {
        return $this->belongsTo(BacklinkAudit::class);
    }
}
