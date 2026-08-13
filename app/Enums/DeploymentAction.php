<?php

namespace App\Enums;

enum DeploymentAction: string
{
    case Deploy = 'deploy';
    case Rollback = 'rollback';
}
