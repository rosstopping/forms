<?php

namespace Database\Factories;

use App\Models\BusinessProfileConnection;
use App\Models\BusinessProfileReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessProfileReview>
 */
class BusinessProfileReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_profile_connection_id' => BusinessProfileConnection::factory(),
            'google_review_name' => 'accounts/123/locations/456/reviews/'.fake()->unique()->uuid(),
            'reviewer_name' => fake()->name(),
            'star_rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->paragraph(),
            'reviewed_at' => now(),
            'reply_status' => BusinessProfileReview::STATUS_UNANSWERED,
        ];
    }
}
