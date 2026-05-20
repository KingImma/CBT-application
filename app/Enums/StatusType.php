<?php

namespace App\Enums;

enum StatusType: string
{
    case Active = 'active';
    case Trial = 'trial';
    case Suspended = 'suspended';
    case Expired = 'expired';
}
