<?php

use App\Models\ContentGeneration;
use App\Models\ContentPlan;

test('migration moves every Copilot and Amp plan to manual without changing other settings or run history', function (): void {
    $plans = collect([
        ContentPlan::factory()->create(['content_engine' => 'copilot', 'enabled' => true]),
        ContentPlan::factory()->create(['content_engine' => 'copilot', 'enabled' => false]),
        ContentPlan::factory()->create(['content_engine' => 'amp', 'enabled' => true]),
        ContentPlan::factory()->create(['content_engine' => 'amp', 'enabled' => false]),
    ]);
    $original = $plans->mapWithKeys(fn (ContentPlan $plan): array => [$plan->id => $plan->fresh()->getAttributes()]);
    $manual = ContentPlan::factory()->create(['content_engine' => 'manual', 'enabled' => false]);
    $manualAttributes = $manual->fresh()->getAttributes();
    $run = ContentGeneration::factory()->for($plans->first(), 'plan')->create(['engine' => 'copilot']);
    $runAttributes = $run->fresh()->getAttributes();
    $migration = require database_path('migrations/2026_10_19_110950_switch_existing_automated_content_plans_to_manual.php');

    $migration->up();

    foreach ($plans as $plan) {
        expect($plan->fresh()->content_engine)->toBe('manual')
            ->and($plan->fresh()->enabled)->toBeFalse()
            ->and(collect($plan->fresh()->getAttributes())->except(['content_engine', 'enabled', 'updated_at'])->all())
            ->toBe(collect($original[$plan->id])->except(['content_engine', 'enabled', 'updated_at'])->all());
    }
    expect($manual->fresh()->getAttributes())->toBe($manualAttributes)
        ->and($run->fresh()->getAttributes())->toBe($runAttributes);

    $afterMigration = $plans->map(fn (ContentPlan $plan): array => $plan->fresh()->getAttributes())->all();
    $this->travel(1)->days();
    $migration->up();
    $migration->down();

    expect($plans->map(fn (ContentPlan $plan): array => $plan->fresh()->getAttributes())->all())->toBe($afterMigration);
});
