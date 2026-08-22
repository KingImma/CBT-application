<?php

namespace App\Enums;

enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
}
