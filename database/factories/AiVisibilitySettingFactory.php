<?php

namespace Database\Factories;

use App\Models\AiVisibilitySetting;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiVisibilitySetting> */
class AiVisibilitySettingFactory extends Factory
{
    public function definition(): array
    {
        return ['website_id' => Website::factory(), 'enabled' => false, 'providers' => ['openai'], 'frequency_days' => 7, 'brand_name' => 'Rowglo Plumbing & Heating Ltd', 'aliases' => ['Rowglo'], 'services' => ['boiler repairs'], 'locations' => ['Doncaster']];
    }
}
