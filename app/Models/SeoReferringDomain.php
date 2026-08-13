<?php

namespace App\Models;

use Database\Factories\SeoReferringDomainFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoReferringDomain extends Model
{
    /** @use HasFactory<SeoReferringDomainFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'seo_snapshot_id', 'domain', 'domain_rank', 'backlinks_count', 'first_seen', 'last_seen'];

    protected function casts(): array
    {
        return ['first_seen' => 'datetime', 'last_seen' => 'datetime'];
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
