<?php

namespace Database\Factories;

use App\Enums\DeploymentAction;
use App\Enums\DeploymentMethod;
use App\Enums\DeploymentStatus;
use App\Models\Optimisation;
use App\Models\OptimisationDeployment;
use App\Models\OptimisationVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptimisationDeployment>
 */
class OptimisationDeploymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'optimisation_id' => Optimisation::factory(),
            'optimisation_version_id' => OptimisationVersion::factory(),
            'action' => DeploymentAction::Deploy,
            'method' => DeploymentMethod::Pixel,
            'status' => DeploymentStatus::Succeeded,
            'performed_at' => now(),
        ];
    }
}
