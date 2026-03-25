<?php

namespace App\Enums;

enum StatusType: string
{
    case Active    = 'active';
    case Trail     = 'trial';
    case Suspended = 'suspended';
    case Expired   = 'expired';
}
