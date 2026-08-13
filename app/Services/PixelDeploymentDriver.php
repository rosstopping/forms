<?php

namespace App\Services;

use App\Contracts\DeploymentDriver;
use App\Data\DeploymentResult;
use App\Enums\DeploymentMethod;
use App\Models\Optimisation;

class PixelDeploymentDriver implements DeploymentDriver
{
    public function deploy(Optimisation $optimisation): DeploymentResult
    {
        if ($optimisation->deployment_method !== DeploymentMethod::Pixel) {
            return DeploymentResult::failed('The optimisation is not configured for Pixel deployment.');
        }

        if (! $optimisation->type->isPixelDeployable()) {
            return DeploymentResult::failed('This optimisation type cannot be deployed through Pixel.');
        }

        return DeploymentResult::succeeded();
    }

    public function rollback(Optimisation $optimisation): DeploymentResult
    {
        if ($optimisation->deployment_method !== DeploymentMethod::Pixel) {
            return DeploymentResult::failed('The optimisation is not configured for Pixel deployment.');
        }

        return DeploymentResult::succeeded();
    }
}
