<?php

namespace App\Models;

use Database\Factories\WebsiteAiQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteAiQuestion extends Model
{
    /** @use HasFactory<WebsiteAiQuestionFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'user_id', 'question', 'answer', 'status', 'error'];

    protected $attributes = ['status' => 'processing'];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
