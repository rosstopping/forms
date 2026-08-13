<?php

namespace Database\Factories;

use App\Enums\DeploymentMethod;
use App\Enums\OptimisationStatus;
use App\Enums\OptimisationType;
use App\Models\Optimisation;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Optimisation>
 */
class OptimisationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'url' => fake()->url(),
            'type' => OptimisationType::Title,
            'selector' => null,
            'target_description' => null,
            'attribute' => null,
            'status' => OptimisationStatus::Draft,
            'deployment_method' => DeploymentMethod::Pixel,
        ];
    }
}
