<?php

namespace App\Models;

use Database\Factories\WordpressStaticReleaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WordpressStaticRelease extends Model
{
    /** @use HasFactory<WordpressStaticReleaseFactory> */
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_BUILDING = 'building';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'website_id',
        'created_by',
        'public_id',
        'commit_sha',
        'source_ref',
        'status',
        'storage_path',
        'checksum',
        'size',
        'error',
        'ready_at',
        'activated_at',
    ];

    protected $casts = [
        'ready_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WordpressStaticRelease $release): void {
            $release->public_id ??= 'wsr_'.Str::lower(Str::random(28));
        });
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
