<?php

namespace App\Enums;

enum ProspectAutomationStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Stopped = 'stopped';
}
