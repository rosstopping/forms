<?php

namespace App\Enums;

enum DeploymentMethod: string
{
    case Manual = 'manual';
    case Pixel = 'pixel';
    case Github = 'github';
    case Wordpress = 'wordpress';
}
