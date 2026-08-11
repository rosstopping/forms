<?php

namespace App\Models;

use Database\Factories\FormSubmissionActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmissionActivity extends Model
{
    /** @use HasFactory<FormSubmissionActivityFactory> */
    use HasFactory;

    protected $fillable = ['form_submission_id', 'user_id', 'type', 'description', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
