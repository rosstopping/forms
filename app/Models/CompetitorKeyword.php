<?php

namespace App\Models;

use Database\Factories\CompetitorKeywordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorKeyword extends Model
{
    /** @use HasFactory<CompetitorKeywordFactory> */
    use HasFactory;

    protected $fillable = ['competitor_audit_id', 'fingerprint', 'keyword', 'position', 'our_position', 'ranking_url', 'our_ranking_url', 'comparison', 'search_volume', 'search_intent', 'keyword_difficulty', 'estimated_traffic'];

    protected $attributes = ['comparison' => 'unknown'];

    protected function casts(): array
    {
        return ['estimated_traffic' => 'decimal:4'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(CompetitorAudit::class, 'competitor_audit_id');
    }
}
