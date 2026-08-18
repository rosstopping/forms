<?php

namespace App\Models;

use Database\Factories\ProspectOutreachLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProspectOutreachLink extends Model
{
    /** @use HasFactory<ProspectOutreachLinkFactory> */
    use HasFactory;

    protected $fillable = ['uuid', 'prospect_outreach_delivery_id', 'kind', 'label', 'destination_url', 'first_clicked_at', 'last_clicked_at', 'click_count'];

    protected static function booted(): void
    {
        static::creating(function (ProspectOutreachLink $link): void {
            $link->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['first_clicked_at' => 'datetime', 'last_clicked_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(ProspectOutreachDelivery::class, 'prospect_outreach_delivery_id');
    }
}
