<?php

namespace Database\Seeders;

use App\Models\CompetitorAudit;
use Illuminate\Database\Seeder;

class CompetitorAuditSeeder extends Seeder
{
    public function run(): void
    {
        CompetitorAudit::factory()->create();
    }
}
