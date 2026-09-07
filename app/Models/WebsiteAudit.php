<?php

namespace App\Models;

use Database\Factories\WebsiteAuditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['website_url', 'domain', 'status', 'opportunity_score', 'findings', 'contact_details', 'analysis_error', 'started_at', 'completed_at', 'expires_at'])]
class WebsiteAudit extends Model
{
    /** @use HasFactory<WebsiteAuditFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $attributes = ['status' => self::STATUS_PENDING];

    protected static function booted(): void
    {
        static::creating(function (WebsiteAudit $audit): void {
            $audit->public_id ??= 'wa_'.Str::lower(Str::random(30));
        });
    }

    protected function casts(): array
    {
        return [
            'findings' => 'array',
            'contact_details' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
