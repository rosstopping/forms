<?php

namespace Database\Seeders;

use App\Models\CompetitorPage;
use Illuminate\Database\Seeder;

class CompetitorPageSeeder extends Seeder
{
    public function run(): void
    {
        CompetitorPage::factory()->create();
    }
}
