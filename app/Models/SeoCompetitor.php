<?php

namespace App\Models;

use Database\Factories\SeoCompetitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoCompetitor extends Model
{
    /** @use HasFactory<SeoCompetitorFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'seo_snapshot_id', 'domain', 'common_keywords', 'organic_keywords', 'estimated_traffic', 'competition_level'];

    protected function casts(): array
    {
        return ['estimated_traffic' => 'decimal:4', 'competition_level' => 'decimal:6'];
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
