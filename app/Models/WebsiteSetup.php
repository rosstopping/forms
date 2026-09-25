<?php

namespace App\Models;

use Database\Factories\WebsiteSetupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSetup extends Model
{
    /** @use HasFactory<WebsiteSetupFactory> */
    use HasFactory;

    public const STEPS = [
        'business' => 'The business',
        'goals' => 'Business goals',
        'connection' => 'Connect the website',
        'google' => 'Google connections',
        'targets' => 'Keywords & competitors',
        'content' => 'Content brief',
        'delivery' => 'Schedule & notifications',
        'review' => 'Review & finish',
    ];

    protected $fillable = ['website_id', 'data', 'saved_steps', 'current_step', 'completed_at'];

    protected $attributes = ['current_step' => 'business'];

    protected function casts(): array
    {
        return ['data' => 'array', 'saved_steps' => 'array', 'completed_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
