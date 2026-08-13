<?php

namespace App\Contracts;

use App\Data\DeploymentResult;
use App\Models\Optimisation;

interface DeploymentDriver
{
    public function deploy(Optimisation $optimisation): DeploymentResult;

    public function rollback(Optimisation $optimisation): DeploymentResult;
}
