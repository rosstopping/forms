<?php

namespace Database\Seeders;

use App\Models\CompetitorOpportunity;
use Illuminate\Database\Seeder;

class CompetitorOpportunitySeeder extends Seeder
{
    public function run(): void
    {
        CompetitorOpportunity::factory()->create();
    }
}
