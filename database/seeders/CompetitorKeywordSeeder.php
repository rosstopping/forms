<?php

namespace Database\Seeders;

use App\Models\CompetitorKeyword;
use Illuminate\Database\Seeder;

class CompetitorKeywordSeeder extends Seeder
{
    public function run(): void
    {
        CompetitorKeyword::factory()->create();
    }
}
