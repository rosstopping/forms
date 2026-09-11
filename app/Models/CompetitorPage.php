<?php

namespace App\Models;

use Database\Factories\CompetitorPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorPage extends Model
{
    /** @use HasFactory<CompetitorPageFactory> */
    use HasFactory;

    protected $fillable = ['competitor_audit_id', 'url', 'url_hash', 'estimated_traffic', 'organic_keywords', 'status', 'analysis', 'error', 'fetched_at'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['analysis' => 'array', 'fetched_at' => 'datetime', 'estimated_traffic' => 'decimal:4'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(CompetitorAudit::class, 'competitor_audit_id');
    }
}
