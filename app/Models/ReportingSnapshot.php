<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingSnapshot extends Model
{
    protected $fillable = ['website_id', 'source', 'property', 'property_hash', 'period_start', 'period_end', 'metrics'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'metrics' => 'array'];
    }
}
