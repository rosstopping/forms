<?php

namespace App\Enums;

enum DeploymentStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
