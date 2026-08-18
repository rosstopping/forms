<?php

namespace Database\Seeders;

use App\Models\ProspectOutreachDelivery;
use Illuminate\Database\Seeder;

class ProspectOutreachDeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProspectOutreachDelivery::factory(10)->create();
    }
}
