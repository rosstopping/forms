<?php

namespace Database\Seeders;

use App\Models\WebsiteCompetitor;
use Illuminate\Database\Seeder;

class WebsiteCompetitorSeeder extends Seeder
{
    public function run(): void
    {
        WebsiteCompetitor::factory()->create();
    }
}
