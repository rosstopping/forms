<?php

namespace App\Models;

use App\Enums\DeploymentAction;
use App\Enums\DeploymentMethod;
use App\Enums\DeploymentStatus;
use Database\Factories\OptimisationDeploymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptimisationDeployment extends Model
{
    /** @use HasFactory<OptimisationDeploymentFactory> */
    use HasFactory;

    protected $fillable = [
        'optimisation_id',
        'optimisation_version_id',
        'action',
        'method',
        'status',
        'message',
        'performed_by',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => DeploymentAction::class,
            'method' => DeploymentMethod::class,
            'status' => DeploymentStatus::class,
            'performed_at' => 'datetime',
        ];
    }

    public function optimisation(): BelongsTo
    {
        return $this->belongsTo(Optimisation::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(OptimisationVersion::class, 'optimisation_version_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
