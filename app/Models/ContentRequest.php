<?php

namespace App\Models;

use Database\Factories\ContentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContentRequest extends Model
{
    /** @use HasFactory<ContentRequestFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'created_by', 'content_generation_id', 'instructions', 'picked_up_at'];

    protected function casts(): array
    {
        return ['picked_up_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(ContentGeneration::class, 'content_generation_id');
    }

    public function searchOpportunity(): HasOne
    {
        return $this->hasOne(SearchOpportunity::class);
    }

    public function seoOpportunity(): HasOne
    {
        return $this->hasOne(SeoOpportunity::class);
    }
}
