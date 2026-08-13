<?php

namespace App\Models;

use Database\Factories\OptimisationVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptimisationVersion extends Model
{
    /** @use HasFactory<OptimisationVersionFactory> */
    use HasFactory;

    protected $fillable = ['optimisation_id', 'version', 'original_value', 'new_value', 'created_by'];

    public function optimisation(): BelongsTo
    {
        return $this->belongsTo(Optimisation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
