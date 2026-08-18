<?php

namespace App\Models;

use Database\Factories\ProspectOutreachDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProspectOutreachDelivery extends Model
{
    /** @use HasFactory<ProspectOutreachDeliveryFactory> */
    use HasFactory;

    protected $fillable = ['uuid', 'prospect_id', 'recipient_email', 'sent_at', 'first_opened_at', 'last_opened_at', 'open_count', 'first_clicked_at', 'last_clicked_at', 'click_count'];

    protected static function booted(): void
    {
        static::creating(function (ProspectOutreachDelivery $delivery): void {
            $delivery->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'first_opened_at' => 'datetime', 'last_opened_at' => 'datetime', 'first_clicked_at' => 'datetime', 'last_clicked_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ProspectOutreachLink::class);
    }
}
