<?php

namespace Database\Factories;

use App\Models\WebsiteAudit;
use App\Models\WebsiteAuditVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebsiteAuditVisit> */
class WebsiteAuditVisitFactory extends Factory
{
    public function definition(): array
    {
        return ['website_audit_id' => WebsiteAudit::factory(), 'visit_id' => fake()->uuid()];
    }
}
