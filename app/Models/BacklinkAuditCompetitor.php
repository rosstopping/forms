<?php

namespace App\Models;

use Database\Factories\BacklinkAuditCompetitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacklinkAuditCompetitor extends Model
{
    /** @use HasFactory<BacklinkAuditCompetitorFactory> */
    use HasFactory;

    protected $fillable = ['backlink_audit_id', 'website_competitor_id', 'domain'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(BacklinkAudit::class, 'backlink_audit_id');
    }

    public function websiteCompetitor(): BelongsTo
    {
        return $this->belongsTo(WebsiteCompetitor::class);
    }
}
