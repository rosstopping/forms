<?php

namespace Database\Seeders;

use App\Models\ProspectOutreachLink;
use Illuminate\Database\Seeder;

class ProspectOutreachLinkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProspectOutreachLink::factory(10)->create();
    }
}
