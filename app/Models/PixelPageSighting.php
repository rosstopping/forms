<?php

namespace App\Models;

use Database\Factories\PixelPageSightingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PixelPageSighting extends Model
{
    /** @use HasFactory<PixelPageSightingFactory> */
    use HasFactory;

    protected $fillable = [
        'website_id',
        'url_hash',
        'url',
        'hostname',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
