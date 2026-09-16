<?php

namespace App\Models;

use Database\Factories\AiVisibilityPromptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AiVisibilityPrompt extends Model
{
    /** @use HasFactory<AiVisibilityPromptFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['website_id', 'seo_target_keyword_id', 'prompt', 'topic', 'location', 'priority', 'active'];

    protected $attributes = ['active' => true, 'priority' => 'normal'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $prompt): void {
            $prompt->prompt = Str::squish($prompt->prompt);
            $prompt->fingerprint = self::fingerprint($prompt->prompt);
        });
    }

    public static function fingerprint(string $prompt): string
    {
        return hash('sha256', Str::lower(trim(preg_replace('/[^\pL\pN]+/u', ' ', $prompt))));
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function targetKeyword(): BelongsTo
    {
        return $this->belongsTo(SeoTargetKeyword::class, 'seo_target_keyword_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AiVisibilityResult::class);
    }
}
