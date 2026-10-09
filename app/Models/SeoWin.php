<?php

namespace App\Models;

use Database\Factories\SeoWinFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoWin extends Model
{
    /** @use HasFactory<SeoWinFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'seo_target_keyword_id', 'fingerprint', 'rule', 'rule_version', 'importance', 'confidence', 'title', 'evidence', 'observed_at', 'confirmed_at', 'client_draft', 'approved_at', 'approved_by', 'shared_at', 'shared_by', 'shared_text', 'dismissed_at'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'rule_version' => 'integer', 'observed_at' => 'datetime', 'confirmed_at' => 'datetime', 'approved_at' => 'datetime', 'shared_at' => 'datetime', 'dismissed_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
