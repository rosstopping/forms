<?php

namespace App\Models;

use Database\Factories\JevShadowEvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JevShadowEvaluation extends Model
{
    /** @use HasFactory<JevShadowEvaluationFactory> */
    use HasFactory;

    protected $fillable = [
        'website_id', 'ai_visibility_result_id', 'input_hash', 'model', 'question_version',
        'request_snapshot', 'baseline_snapshot', 'status', 'returned_model', 'answers',
        'mention_probability', 'reference_choice', 'choice_confidence', 'usage', 'cost',
        'cost_currency', 'latency_ms', 'http_status', 'error_type', 'started_at', 'completed_at',
        'human_label', 'ambiguity', 'adjudication', 'outcome_references', 'reviewed_at',
    ];

    protected $attributes = ['status' => 'running'];

    protected function casts(): array
    {
        return [
            'request_snapshot' => 'array', 'baseline_snapshot' => 'array', 'answers' => 'array',
            'usage' => 'array', 'mention_probability' => 'float', 'choice_confidence' => 'float',
            'cost' => 'decimal:8', 'human_label' => 'boolean', 'adjudication' => 'array',
            'outcome_references' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityResult::class, 'ai_visibility_result_id');
    }
}
