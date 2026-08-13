<?php

namespace App\Services;

use App\Contracts\DeploymentDriver;
use App\Enums\DeploymentAction;
use App\Enums\DeploymentMethod;
use App\Enums\DeploymentStatus;
use App\Enums\OptimisationStatus;
use App\Models\Optimisation;
use App\Models\OptimisationDeployment;
use App\Models\OptimisationVersion;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReportPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

class OptimisationDeploymentManager
{
    public function __construct(private PixelDeploymentDriver $pixelDriver) {}

    /** @param array{type: string, selector?: ?string, target_description?: ?string, attribute?: ?string, original_value?: ?string, new_value: string} $data */
    public function create(
        Website $website,
        WebsiteHealthReportPage $page,
        array $data,
        ?User $author = null,
    ): Optimisation {
        return DB::transaction(function () use ($website, $page, $data, $author): Optimisation {
            $optimisation = $website->optimisations()->create([
                'website_health_report_page_id' => $page->id,
                'url' => $page->url,
                'type' => $data['type'],
                'selector' => $data['selector'] ?? null,
                'target_description' => $data['target_description'] ?? null,
                'attribute' => $data['attribute'] ?? null,
                'status' => OptimisationStatus::Draft,
                'deployment_method' => DeploymentMethod::Pixel,
            ]);

            $this->createVersion(
                $optimisation,
                $data['new_value'],
                $data['original_value'] ?? null,
                $author,
            );

            return $optimisation->refresh();
        });
    }

    public function createVersion(
        Optimisation $optimisation,
        string $newValue,
        ?string $originalValue = null,
        ?User $author = null,
    ): OptimisationVersion {
        return DB::transaction(function () use ($optimisation, $newValue, $originalValue, $author): OptimisationVersion {
            $lockedOptimisation = Optimisation::query()->lockForUpdate()->findOrFail($optimisation->id);

            if ($lockedOptimisation->status === OptimisationStatus::Deployed) {
                throw new LogicException('A live optimisation must be rolled back before it can be revised.');
            }

            $latestVersion = $lockedOptimisation->versions()->latest('version')->first();
            $version = $lockedOptimisation->versions()->create([
                'version' => ($latestVersion?->version ?? 0) + 1,
                'original_value' => $latestVersion?->new_value ?? $originalValue,
                'new_value' => $newValue,
                'created_by' => $author?->id,
            ]);

            $lockedOptimisation->update([
                'status' => OptimisationStatus::Draft,
                'approved_at' => null,
                'deployed_at' => null,
                'rolled_back_at' => null,
            ]);

            return $version;
        });
    }

    public function approve(Optimisation $optimisation): Optimisation
    {
        if ($optimisation->status === OptimisationStatus::Deployed) {
            throw new LogicException('A live optimisation is already approved and deployed.');
        }

        if (! $optimisation->versions()->exists()) {
            throw new LogicException('An optimisation needs a version before it can be approved.');
        }

        $optimisation->update([
            'status' => OptimisationStatus::Approved,
            'approved_at' => now(),
        ]);

        return $optimisation->refresh();
    }

    public function deploy(Optimisation $optimisation, ?User $performer = null): OptimisationDeployment
    {
        return $this->perform($optimisation, DeploymentAction::Deploy, $performer);
    }

    public function rollback(Optimisation $optimisation, ?User $performer = null): OptimisationDeployment
    {
        return $this->perform($optimisation, DeploymentAction::Rollback, $performer);
    }

    public function rollbackPage(WebsiteHealthReportPage $page, ?User $performer = null): int
    {
        $optimisations = $page->optimisations()
            ->where('status', OptimisationStatus::Deployed)
            ->where('deployment_method', DeploymentMethod::Pixel)
            ->orderBy('id')
            ->get();

        foreach ($optimisations as $optimisation) {
            $this->rollback($optimisation, $performer);
        }

        return $optimisations->count();
    }

    private function perform(
        Optimisation $optimisation,
        DeploymentAction $action,
        ?User $performer,
    ): OptimisationDeployment {
        return DB::transaction(function () use ($optimisation, $action, $performer): OptimisationDeployment {
            $lockedOptimisation = Optimisation::query()
                ->with('currentVersion')
                ->lockForUpdate()
                ->findOrFail($optimisation->id);

            $requiredStatus = $action === DeploymentAction::Deploy
                ? OptimisationStatus::Approved
                : OptimisationStatus::Deployed;

            if ($lockedOptimisation->status !== $requiredStatus || ! $lockedOptimisation->currentVersion) {
                throw new LogicException("The optimisation cannot be {$action->value}ed from its current state.");
            }

            $result = $action === DeploymentAction::Deploy
                ? $this->driverFor($lockedOptimisation->deployment_method)->deploy($lockedOptimisation)
                : $this->driverFor($lockedOptimisation->deployment_method)->rollback($lockedOptimisation);

            $deployment = $lockedOptimisation->deployments()->create([
                'optimisation_version_id' => $lockedOptimisation->currentVersion->id,
                'action' => $action,
                'method' => $lockedOptimisation->deployment_method,
                'status' => $result->successful ? DeploymentStatus::Succeeded : DeploymentStatus::Failed,
                'message' => $result->message,
                'performed_by' => $performer?->id,
                'performed_at' => now(),
            ]);

            if ($result->successful) {
                $lockedOptimisation->update($action === DeploymentAction::Deploy ? [
                    'status' => OptimisationStatus::Deployed,
                    'deployed_at' => now(),
                    'rolled_back_at' => null,
                ] : [
                    'status' => OptimisationStatus::RolledBack,
                    'rolled_back_at' => now(),
                ]);
                $lockedOptimisation->website()->increment('pixel_payload_version');
                Log::info('Pixel optimisation lifecycle changed.', [
                    'optimisation' => $lockedOptimisation->public_id,
                    'website_id' => $lockedOptimisation->website_id,
                    'action' => $action->value,
                    'version' => $lockedOptimisation->currentVersion->version,
                ]);
            } else {
                $lockedOptimisation->update(['status' => OptimisationStatus::Failed]);
            }

            return $deployment;
        });
    }

    private function driverFor(DeploymentMethod $method): DeploymentDriver
    {
        return match ($method) {
            DeploymentMethod::Pixel => $this->pixelDriver,
            default => throw new LogicException("The {$method->value} deployment driver is not implemented."),
        };
    }
}
