<?php

namespace App\Models;

use Database\Factories\LeadTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class LeadTag extends Model
{
    /** @use HasFactory<LeadTagFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'name', 'normalized_name'];

    protected static function booted(): void
    {
        static::saving(function (LeadTag $tag): void {
            $tag->name = Str::squish($tag->name);
            $tag->normalized_name = Str::lower($tag->name);
        });
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function submissions(): BelongsToMany
    {
        return $this->belongsToMany(FormSubmission::class);
    }
}
