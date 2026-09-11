<?php

namespace App\Models;

use Database\Factories\BacklinkLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacklinkLink extends Model
{
    /** @use HasFactory<BacklinkLinkFactory> */
    use HasFactory;

    protected $fillable = ['backlink_audit_id', 'fingerprint', 'state', 'source_domain', 'source_url', 'target_url', 'anchor', 'dofollow', 'broken', 'source_domain_rank', 'source_page_rank', 'spam_score', 'links_count', 'semantic_location', 'first_seen', 'last_seen'];

    protected function casts(): array
    {
        return ['dofollow' => 'boolean', 'broken' => 'boolean', 'first_seen' => 'datetime', 'last_seen' => 'datetime'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(BacklinkAudit::class, 'backlink_audit_id');
    }
}
