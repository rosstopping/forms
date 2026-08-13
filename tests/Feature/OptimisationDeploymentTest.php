<?php

use App\Enums\DeploymentAction;
use App\Enums\DeploymentMethod;
use App\Enums\DeploymentStatus;
use App\Enums\OptimisationStatus;
use App\Enums\OptimisationType;
use App\Models\Optimisation;
use App\Models\User;
use App\Models\Website;
use App\Services\OptimisationDeploymentManager;
use LogicException;

it('creates safe public identifiers for websites and optimisations', function (): void {
    $website = Website::factory()->create();
    $optimisation = Optimisation::factory()->for($website)->create();

    expect($website->pixel_public_key)
        ->toStartWith('sw_')
        ->toHaveLength(31)
        ->not->toBe('sw_'.$website->id)
        ->and($optimisation->public_id)
        ->toStartWith('opt_')
        ->toHaveLength(28)
        ->not->toBe('opt_'.$optimisation->id);
});

it('approves deploys and rolls back a pixel optimisation with an append-only history', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->create();
    $optimisation = Optimisation::factory()->for($website)->create([
        'url' => 'https://example.com/services/roof-repairs',
        'type' => OptimisationType::Title,
        'deployment_method' => DeploymentMethod::Pixel,
    ]);
    $manager = app(OptimisationDeploymentManager::class);

    $version = $manager->createVersion(
        $optimisation,
        'Roof Repairs in Doncaster',
        'Roof Repairs',
        $user,
    );
    $manager->approve($optimisation->refresh());
    $deployment = $manager->deploy($optimisation->refresh(), $user);
    $rollback = $manager->rollback($optimisation->refresh(), $user);

    expect($version->version)->toBe(1)
        ->and($version->original_value)->toBe('Roof Repairs')
        ->and($version->new_value)->toBe('Roof Repairs in Doncaster')
        ->and($deployment->action)->toBe(DeploymentAction::Deploy)
        ->and($deployment->status)->toBe(DeploymentStatus::Succeeded)
        ->and($rollback->action)->toBe(DeploymentAction::Rollback)
        ->and($rollback->optimisation_version_id)->toBe($version->id)
        ->and($optimisation->refresh()->status)->toBe(OptimisationStatus::RolledBack)
        ->and($optimisation->deployments()->count())->toBe(2)
        ->and($website->refresh()->pixel_payload_version)->toBe(3);
});

it('retains every value when an optimisation is revised', function (): void {
    $optimisation = Optimisation::factory()->create();
    $manager = app(OptimisationDeploymentManager::class);

    $manager->createVersion($optimisation, 'Roof Repairs in Doncaster', 'Roof Repairs');
    $secondVersion = $manager->createVersion($optimisation->refresh(), 'Emergency Roof Repairs in Doncaster');

    expect($optimisation->versions()->orderBy('version')->get())
        ->toHaveCount(2)
        ->sequence(
            fn ($version) => $version
                ->version->toBe(1)
                ->original_value->toBe('Roof Repairs')
                ->new_value->toBe('Roof Repairs in Doncaster'),
            fn ($version) => $version
                ->version->toBe(2)
                ->original_value->toBe('Roof Repairs in Doncaster')
                ->new_value->toBe('Emergency Roof Repairs in Doncaster'),
        )
        ->and($secondVersion->version)->toBe(2);
});

it('requires approval before deployment and rollback requires a live optimisation', function (): void {
    $optimisation = Optimisation::factory()->create();
    $manager = app(OptimisationDeploymentManager::class);
    $manager->createVersion($optimisation, 'New title', 'Old title');

    expect(fn () => $manager->deploy($optimisation->refresh()))
        ->toThrow(LogicException::class)
        ->and(fn () => $manager->rollback($optimisation->refresh()))
        ->toThrow(LogicException::class)
        ->and($optimisation->deployments()->count())->toBe(0);
});

it('does not allow a deployed value to be overwritten', function (): void {
    $optimisation = Optimisation::factory()->create();
    $manager = app(OptimisationDeploymentManager::class);
    $manager->createVersion($optimisation, 'New title', 'Old title');
    $manager->approve($optimisation->refresh());
    $manager->deploy($optimisation->refresh());

    expect(fn () => $manager->createVersion($optimisation->refresh(), 'Another title'))
        ->toThrow(LogicException::class)
        ->and($optimisation->versions()->count())->toBe(1);
});
