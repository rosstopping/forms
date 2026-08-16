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

    protected $fillable = [
        'website_id', 'user_id', 'question', 'answer', 'status', 'error', 'failure_type', 'failure_detail', 'report_reason', 'reported_at',
        'credited_at', 'credited_by_user_id',
    ];

    protected $attributes = ['status' => 'processing'];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'credited_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credited_by_user_id');
    }
}
