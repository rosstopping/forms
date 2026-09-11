<?php

namespace App\Models;

use Database\Factories\BacklinkPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacklinkPage extends Model
{
    /** @use HasFactory<BacklinkPageFactory> */
    use HasFactory;

    protected $fillable = ['backlink_audit_id', 'domain', 'kind', 'url', 'url_hash', 'backlinks', 'referring_domains', 'page_rank', 'status', 'analysis', 'error', 'fetched_at'];

    protected function casts(): array
    {
        return ['analysis' => 'array', 'fetched_at' => 'datetime'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(BacklinkAudit::class, 'backlink_audit_id');
    }
}
